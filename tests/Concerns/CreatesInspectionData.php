<?php

namespace Tests\Concerns;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\Inspection;
use App\Models\Institution;
use App\Models\User;
use App\Services\Accreditation\ApplicationWizardService;
use App\Services\Accreditation\ChecklistResponseSynchronizer;
use App\Services\Accreditation\CompletenessReviewService;
use App\Services\Accreditation\InspectionService;

/**
 * Builds synthetic inspection fixtures: an eligible (complete-for-inspection)
 * application with a sectioned checklist version, inspector / PSP / CART / auditor
 * / institution users, and a scheduled inspection whose criteria are snapshotted
 * from the checklist version.
 *
 * All data is fictional; no real institution or PSP criteria are used.
 */
trait CreatesInspectionData
{
    use CreatesCompletenessData;
    use CreatesFoundationData;

    /**
     * An eligible application: active program, complete-for-inspection status,
     * a valid (sectioned) checklist version, and no blocking completeness items.
     *
     * @return array{application: AccreditationApplication, applicant: User, reviewer: User, institution: Institution}
     */
    protected function eligibleApplication(): array
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);

        // A checklist version that actually contains a section + requirements,
        // so the inspection snapshot has something to copy.
        $checklist = $this->synthChecklist();
        $checklistVersionId = $checklist['mandatory']->checklist_version_id;

        $applicant = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $reviewer = $this->userWithRole(RoleName::CartReviewer, $institution);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->withCompletedSections()
            ->create([
                'created_by' => $applicant->id,
                'checklist_version_id' => $checklistVersionId,
                'status' => ApplicationStatus::Draft,
                'submitted_by' => $applicant->id,
            ]);

        app(ChecklistResponseSynchronizer::class)->sync($application);
        $application->refresh()->requirements()->with('requirement')->get()
            ->each(fn ($row) => $row->update([
                'applicant_status' => ApplicantResponseStatus::Complete,
                'applicant_response' => '[SYNTHETIC] Response.',
                'responded_at' => now(),
                'responded_by' => $applicant->id,
            ]));
        $application->forceFill([
            'declaration_accepted_at' => now(),
            'declaration_accepted_by' => $applicant->id,
        ])->save();

        $application = app(ApplicationWizardService::class)
            ->submit($applicant, $application)
            ->refresh();

        $application = app(CompletenessReviewService::class)
            ->beginReview($reviewer, $application)
            ->refresh();
        $application = app(CompletenessReviewService::class)
            ->markComplete($reviewer, $application)
            ->refresh();

        // Freeze the sectioned version so the inspection snapshot is non-empty.
        if ($application->checklist_version_id === null) {
            $application->forceFill(['checklist_version_id' => $checklistVersionId])->save();
            $application->refresh();
        }

        return [
            'application' => $application,
            'applicant' => $applicant,
            'reviewer' => $reviewer,
            'institution' => $institution,
        ];
    }

    protected function inspectorUser(): User
    {
        return $this->userWithRole(RoleName::Inspector);
    }

    protected function pspAdministrator(): User
    {
        return $this->userWithRole(RoleName::PspAdministrator);
    }

    protected function cartReviewer(): User
    {
        return $this->userWithRole(RoleName::CartReviewer);
    }

    protected function auditorUser(): User
    {
        return $this->userWithRole(RoleName::ReadOnlyAuditor);
    }

    /**
     * Create a scheduled inspection for an eligible application.
     */
    protected function scheduledInspection(AccreditationApplication $application, ?User $lead = null): Inspection
    {
        $lead ??= $this->inspectorUser();
        $second = $this->inspectorUser();

        return app(InspectionService::class)->create(
            $this->pspAdministrator(),
            $application,
            [
                'inspection_type' => 'onsite',
                'scheduled_start_at' => now()->addDays(7),
                'scheduled_end_at' => now()->addDays(7)->addHours(2),
                'timezone' => 'Asia/Manila',
                'location' => '[SYNTHETIC] Venue',
            ],
            $lead->id,
            [$second->id],
        )['inspection'];
    }

    protected function startInspection(Inspection $inspection, ?User $actor = null): Inspection
    {
        return app(InspectionService::class)->start(
            $actor ?? $this->inspectorUser(),
            $inspection,
        );
    }
}
