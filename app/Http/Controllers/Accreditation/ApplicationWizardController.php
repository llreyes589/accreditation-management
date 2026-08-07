<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\ApplicantResponseStatus;
use App\Enums\WizardStep;
use App\Exceptions\ApplicationSubmissionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accreditation\AcceptDeclarationRequest;
use App\Http\Requests\Accreditation\SaveChecklistResponseRequest;
use App\Http\Requests\Accreditation\SaveWizardStepRequest;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Services\Accreditation\ApplicationProgressCalculator;
use App\Services\Accreditation\ApplicationWizardService;
use App\Services\Accreditation\ChecklistResponseSynchronizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/**
 * The multi-step accreditation application wizard.
 *
 * Every step reads from and writes to the database; nothing about the
 * application lives only in the session.
 */
class ApplicationWizardController extends Controller
{
    public function __construct(
        private readonly ApplicationWizardService $wizard,
        private readonly ApplicationProgressCalculator $progress,
        private readonly ChecklistResponseSynchronizer $checklist,
    ) {}

    /**
     * Render the wizard for a draft, resuming at its persisted step.
     */
    public function edit(Request $request, AccreditationApplication $application): Response
    {
        $this->authorize('view', $application);

        $application->load(['institution', 'program']);

        // Backfill responses if the checklist gained requirements since the
        // draft was created. Existing answers are never disturbed.
        if ($request->user()->can('update', $application)) {
            $this->checklist->sync($application);
            $application->refresh();
        }

        return inertia('accreditation/applications/wizard', [
            'application' => $this->presentApplication($application),
            'progress' => $this->progress->summary($application),
            'checklist' => $this->presentChecklist($application),
            'response_statuses' => array_map(
                fn (ApplicantResponseStatus $s): array => [
                    'value' => $s->value,
                    'label' => $s->label(),
                    'requires_explanation' => $s->requiresExplanation(),
                ],
                ApplicantResponseStatus::selectableCases(),
            ),
            'can_edit' => $request->user()->can('update', $application),
            'can_submit' => $request->user()->can('submit', $application),
        ]);
    }

    /**
     * Persist one step of the wizard.
     */
    public function saveStep(
        SaveWizardStepRequest $request,
        AccreditationApplication $application,
    ): RedirectResponse {
        $step = $request->resolveStep();

        $this->wizard->saveStep(
            $request->user(),
            $application,
            $step,
            $request->payload(),
            $request->shouldAdvance() ? ($step->next() ?? $step) : $step,
        );

        return back()->with('success', $step->label().' saved.');
    }

    /**
     * Move between steps without changing data.
     *
     * Navigating backward never discards saved work.
     */
    public function navigate(Request $request, AccreditationApplication $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $validated = $request->validate([
            'step' => ['required', 'string'],
        ]);

        $application->forceFill([
            'current_step' => WizardStep::fromValue($validated['step']),
        ])->save();

        return back();
    }

    /**
     * Record the applicant's answer to a checklist requirement.
     */
    public function saveChecklistResponse(
        SaveChecklistResponseRequest $request,
        AccreditationApplication $application,
    ): RedirectResponse {
        /** @var ApplicationRequirement $row */
        $row = $request->requirementRow();

        $this->wizard->saveChecklistResponse(
            $request->user(),
            $application,
            $row,
            $request->applicantStatus(),
            $request->input('applicant_response'),
            $request->input('applicant_explanation'),
        );

        return back()->with('success', 'Checklist response saved.');
    }

    /**
     * Accept the declaration on the review step.
     */
    public function acceptDeclaration(
        AcceptDeclarationRequest $request,
        AccreditationApplication $application,
    ): RedirectResponse {
        $this->wizard->acceptDeclaration($request->user(), $application);

        return back()->with('success', 'Declaration accepted.');
    }

    /**
     * Submit the application inside a transaction.
     */
    public function submit(Request $request, AccreditationApplication $application): RedirectResponse
    {
        $this->authorize('submit', $application);

        try {
            $submitted = $this->wizard->submit($request->user(), $application);
        } catch (ApplicationSubmissionException $e) {
            throw ValidationException::withMessages([
                $e->field() => $e->getMessage(),
            ]);
        }

        return redirect()->route('applications.confirmation', $submitted);
    }

    /**
     * Post-submission confirmation page.
     */
    public function confirmation(Request $request, AccreditationApplication $application): Response
    {
        $this->authorize('view', $application);

        $application->load(['institution', 'program', 'submitter']);

        return inertia('accreditation/applications/confirmation', [
            'application' => [
                'id' => $application->id,
                'reference' => $application->application_reference,
                'institution' => $application->institution?->name,
                'program' => $application->program?->name,
                'application_type' => $application->application_type->label(),
                'status_label' => $application->status->label(),
                'submitted_at' => $application->submitted_at?->toDayDateTimeString(),
                'submitted_by' => $application->submitter?->name,
                'checklist_version' => $application->checklist_version,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function presentApplication(AccreditationApplication $application): array
    {
        return [
            'id' => $application->id,
            'reference' => $application->application_reference,
            'institution' => $application->institution?->name,
            'program' => $application->program?->name,
            'program_type' => $application->program?->program_type->label(),
            'application_type' => $application->application_type->value,
            'application_type_label' => $application->application_type->label(),
            'checklist_version' => $application->checklist_version,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
            'current_step' => $application->current_step->value,
            'completion_percentage' => $application->completion_percentage,
            'declaration_accepted_at' => $application->declaration_accepted_at?->toDayDateTimeString(),
            'sections' => [
                'program_details' => $application->program_details ?? [],
                'institution_information' => $application->institution_information ?? [],
                'leadership_staffing' => $application->leadership_staffing ?? [],
                'facilities_services' => $application->facilities_services ?? [],
                'curriculum_training' => $application->curriculum_training ?? [],
            ],
        ];
    }

    /**
     * Checklist rows grouped by section, applicant-facing fields only.
     *
     * Reviewer status is deliberately excluded from the applicant payload.
     *
     * @return array<int, array<string, mixed>>
     */
    private function presentChecklist(AccreditationApplication $application): array
    {
        $rows = $application->requirements()
            ->with(['requirement.section'])
            ->get()
            ->filter(fn (ApplicationRequirement $r): bool => $r->requirement !== null
                && ! $r->requirement->is_reviewer_only);

        return $rows
            ->groupBy(function (ApplicationRequirement $r): string {
                $section = $r->requirement?->section;

                return $section === null ? 'General' : $section->title;
            })
            ->map(fn ($group, string $title): array => [
                'section' => $title,
                'items' => $group
                    ->sortBy(fn (ApplicationRequirement $r): int => $r->requirement->position)
                    ->map(fn (ApplicationRequirement $r): array => [
                        'id' => $r->id,
                        'code' => $r->requirement->code,
                        'requirement' => $r->requirement->requirement,
                        'guidance' => $r->requirement->guidance,
                        'severity' => $r->requirement->severity->value,
                        'severity_label' => $r->requirement->severity->label(),
                        'is_mandatory' => $r->requirement->is_mandatory,
                        'applicant_status' => $r->applicant_status->value,
                        'applicant_response' => $r->applicant_response,
                        'applicant_explanation' => $r->applicant_explanation,
                        'responded_at' => $r->responded_at?->toDayDateTimeString(),
                    ])
                    ->values()
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
