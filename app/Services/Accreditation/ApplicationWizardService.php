<?php

namespace App\Services\Accreditation;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\AuditAction;
use App\Enums\ProgramStatus;
use App\Enums\WizardStep;
use App\Exceptions\ApplicationSubmissionException;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Models\Program;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ChecklistVersionService;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the accreditation application wizard.
 *
 * All state lives in MySQL. Every mutation is audit logged, and submission
 * runs inside a single database transaction so a failed validation leaves no
 * partial status change behind.
 */
class ApplicationWizardService
{
    public function __construct(
        private readonly ApplicationProgressCalculator $progress,
        private readonly ChecklistResponseSynchronizer $checklist,
        private readonly ApplicationReferenceGenerator $references,
        private readonly ChecklistVersionService $versions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create a draft application and persist it immediately.
     */
    public function createDraft(User $user, Program $program, ApplicationType $type): AccreditationApplication
    {
        return DB::transaction(function () use ($user, $program, $type): AccreditationApplication {
            if ($this->checklist->hasDuplicateActiveApplication($program, $type)) {
                throw ApplicationSubmissionException::duplicateActiveApplication();
            }

            // Freeze the published checklist edition for the life of the
            // application. Institution users cannot choose an arbitrary
            // version; the system assigns the current published one.
            $version = $this->versions->publishedVersionForProgramType($program->program_type);

            if ($version === null) {
                throw ApplicationSubmissionException::noChecklistVersion();
            }

            $application = AccreditationApplication::create([
                'institution_id' => $program->institution_id,
                'program_id' => $program->id,
                'application_type' => $type,
                'checklist_version' => $version->version_code,
                'checklist_version_id' => $version->id,
                'application_reference' => $this->references->generate($program),
                'status' => ApplicationStatus::Draft,
                'current_step' => WizardStep::ApplicationDetails,
                'completion_percentage' => 0,
                'created_by' => $user->id,
            ]);

            $this->checklist->sync($application);
            $this->refreshProgress($application);

            $this->audit->log(
                AuditAction::ApplicationCreated,
                $user,
                $application,
                $application->institution_id,
                [
                    'application_reference' => $application->application_reference,
                    'application_type' => $type->value,
                    'program_id' => $program->id,
                ],
            );

            return $application->refresh();
        });
    }

    /**
     * Persist one wizard step's free-form payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function saveStep(
        User $user,
        AccreditationApplication $application,
        WizardStep $step,
        array $payload,
        ?WizardStep $advanceTo = null,
    ): AccreditationApplication {
        return DB::transaction(function () use ($user, $application, $step, $payload, $advanceTo): AccreditationApplication {
            $column = $step->payloadColumn();

            if ($column !== null) {
                // Merge so a partially completed step is not wiped by a later
                // save of the same section.
                $existing = $application->getAttribute($column) ?? [];
                $application->setAttribute($column, array_merge($existing, $payload));
            }

            $application->current_step = $advanceTo ?? $step;
            $application->save();

            $this->refreshProgress($application);

            $this->audit->log(
                AuditAction::ApplicationStepSaved,
                $user,
                $application,
                $application->institution_id,
                [
                    'step' => $step->value,
                    // Field names only: never the submitted values.
                    'fields' => array_keys($payload),
                ],
            );

            return $application->refresh();
        });
    }

    /**
     * Record the applicant's answer to a single checklist requirement.
     */
    public function saveChecklistResponse(
        User $user,
        AccreditationApplication $application,
        ApplicationRequirement $row,
        ApplicantResponseStatus $status,
        ?string $response,
        ?string $explanation,
    ): ApplicationRequirement {
        return DB::transaction(function () use ($user, $application, $row, $status, $response, $explanation): ApplicationRequirement {
            $previous = $row->applicant_status;

            // Only applicant-side columns are ever written here. Reviewer
            // status is untouched by the wizard.
            $row->fill([
                'applicant_status' => $status,
                'applicant_response' => $response,
                'applicant_explanation' => $status->requiresExplanation() ? $explanation : null,
                'responded_at' => now(),
                'responded_by' => $user->id,
            ])->save();

            $this->refreshProgress($application);

            $this->audit->log(
                AuditAction::ChecklistResponseChanged,
                $user,
                $row,
                $application->institution_id,
                [
                    'application_reference' => $application->application_reference,
                    'checklist_requirement_id' => $row->checklist_requirement_id,
                    'from_status' => $previous?->value,
                    'to_status' => $status->value,
                ],
            );

            return $row->refresh();
        });
    }

    /**
     * Accept the applicant declaration.
     */
    public function acceptDeclaration(User $user, AccreditationApplication $application): AccreditationApplication
    {
        return DB::transaction(function () use ($user, $application): AccreditationApplication {
            $application->forceFill([
                'declaration_accepted_at' => now(),
                'declaration_accepted_by' => $user->id,
                'current_step' => WizardStep::SubmitApplication,
            ])->save();

            $this->refreshProgress($application);

            $this->audit->log(
                AuditAction::DeclarationAccepted,
                $user,
                $application,
                $application->institution_id,
                ['application_reference' => $application->application_reference],
            );

            return $application->refresh();
        });
    }

    /**
     * Submit the application.
     *
     * Every precondition is re-checked inside the transaction against freshly
     * locked data, so a concurrent edit cannot slip past validation.
     *
     * @throws ApplicationSubmissionException
     */
    public function submit(User $user, AccreditationApplication $application): AccreditationApplication
    {
        return DB::transaction(function () use ($user, $application): AccreditationApplication {
            /** @var AccreditationApplication $locked */
            $locked = AccreditationApplication::query()
                ->whereKey($application->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertSubmittable($user, $locked);

            $locked->forceFill([
                'status' => ApplicationStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $user->id,
                'current_step' => WizardStep::SubmitApplication,
                'completion_percentage' => 100,
            ])->save();

            $this->audit->log(
                AuditAction::ApplicationSubmitted,
                $user,
                $locked,
                $locked->institution_id,
                [
                    'application_reference' => $locked->application_reference,
                    'application_type' => $locked->application_type->value,
                    'checklist_version' => $locked->checklist_version,
                    'from_status' => ApplicationStatus::Draft->value,
                    'to_status' => ApplicationStatus::Submitted->value,
                ],
            );

            return $locked->refresh();
        });
    }

    /**
     * Withdraw a draft before submission.
     */
    public function withdrawDraft(User $user, AccreditationApplication $application): AccreditationApplication
    {
        return DB::transaction(function () use ($user, $application): AccreditationApplication {
            if ($application->status !== ApplicationStatus::Draft) {
                throw ApplicationSubmissionException::notADraft();
            }

            $application->forceFill([
                'status' => ApplicationStatus::Withdrawn,
                'withdrawn_at' => now(),
                'withdrawn_by' => $user->id,
            ])->save();

            $this->audit->log(
                AuditAction::ApplicationWithdrawn,
                $user,
                $application,
                $application->institution_id,
                [
                    'application_reference' => $application->application_reference,
                    'from_status' => ApplicationStatus::Draft->value,
                    'to_status' => ApplicationStatus::Withdrawn->value,
                ],
            );

            return $application->refresh();
        });
    }

    /**
     * Validate every submission precondition.
     *
     * @throws ApplicationSubmissionException
     */
    public function assertSubmittable(User $user, AccreditationApplication $application): void
    {
        if (! $user->belongsToInstitution($application->institution_id)) {
            throw ApplicationSubmissionException::foreignInstitution();
        }

        if ($application->status !== ApplicationStatus::Draft) {
            throw ApplicationSubmissionException::notADraft();
        }

        $program = $application->program;

        if ($program === null) {
            throw ApplicationSubmissionException::missingProgram();
        }

        if (! in_array($program->status, [ProgramStatus::Active, ProgramStatus::Accredited], true)) {
            throw ApplicationSubmissionException::inactiveProgram();
        }

        $incompleteSteps = [];

        foreach (WizardStep::completableSteps() as $step) {
            if ($step === WizardStep::ChecklistResponses || $step === WizardStep::ReviewDeclaration) {
                continue;
            }

            if (! $this->progress->isStepComplete($application, $step)) {
                $incompleteSteps[] = $step->label();
            }
        }

        if ($incompleteSteps !== []) {
            throw ApplicationSubmissionException::incompleteSteps($incompleteSteps);
        }

        $outstanding = $this->progress->outstandingMandatoryCount($application);

        if ($outstanding > 0) {
            throw ApplicationSubmissionException::incompleteChecklist($outstanding);
        }

        if (! $application->declarationAccepted()) {
            throw ApplicationSubmissionException::declarationRequired();
        }

        if ($this->checklist->hasDuplicateActiveApplication(
            $program,
            $application->application_type,
            $application->getKey(),
        )) {
            throw ApplicationSubmissionException::duplicateActiveApplication();
        }
    }

    /**
     * Recompute and persist the completion percentage from stored data.
     */
    public function refreshProgress(AccreditationApplication $application): void
    {
        $application->forceFill([
            'completion_percentage' => $this->progress->percentage($application->refresh()),
        ])->saveQuietly();
    }
}
