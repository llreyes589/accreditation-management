<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Exceptions\ApplicationSubmissionException;
use App\Exceptions\CompletenessReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accreditation\StoreApplicationRequest;
use App\Models\AccreditationApplication;
use App\Models\Program;
use App\Services\Accreditation\ApplicationWizardService;
use App\Services\Accreditation\CompletenessReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/**
 * Application list, start page, and draft creation.
 */
class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationWizardService $wizard,
        private readonly CompletenessReviewService $reviews,
    ) {}

    /**
     * List applications visible to the current user.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();
        $this->authorize('viewAny', AccreditationApplication::class);

        $query = AccreditationApplication::query()
            ->with(['institution', 'program']);

        // Institution isolation: scoped users never see other institutions.
        if (! $user->hasCrossInstitutionRole()) {
            $query->whereIn('institution_id', $user->institutionIds());
        }

        $applications = $query
            ->orderByDesc('updated_at')
            ->get()
            ->filter(fn (AccreditationApplication $a): bool => $user->can('view', $a))
            ->map(fn (AccreditationApplication $a): array => [
                'id' => $a->id,
                'reference' => $a->application_reference,
                'institution' => $a->institution?->name,
                'program' => $a->program?->name,
                'program_type' => $a->program?->program_type->label(),
                'application_type' => $a->application_type->label(),
                'status' => $a->status->value,
                'status_label' => $a->status->label(),
                'current_step' => $a->current_step->label(),
                'current_step_position' => $a->current_step->position(),
                'completion_percentage' => $a->completion_percentage,
                'updated_at' => $a->updated_at?->toDayDateTimeString(),
                'submitted_at' => $a->submitted_at?->toDayDateTimeString(),
                'can_edit' => $user->can('update', $a),
                'can_withdraw' => $user->can('withdraw', $a),
                'can_resubmit' => $user->can('resubmitCompletenessReview', $a),
                'returned_comment' => $a->status === ApplicationStatus::ReturnedForRevision
                    ? $a->returned_comment
                    : null,
            ])
            ->values();

        return inertia('accreditation/applications/index', [
            'applications' => $applications,
            'can_create' => $user->can('create', AccreditationApplication::class),
        ]);
    }

    /**
     * Start-application page: choose an eligible program and application type.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', AccreditationApplication::class);

        $user = $request->user();

        // Only programs belonging to the user's own institutions are offered.
        $programs = Program::query()
            ->with('institution')
            ->whereIn('institution_id', $user->institutionIds())
            ->orderBy('name')
            ->get()
            ->filter(fn (Program $p): bool => $p->status->isSelectableForApplication())
            ->map(fn (Program $p): array => [
                'id' => $p->id,
                'name' => $p->name,
                'type' => $p->program_type->label(),
                'institution' => $p->institution?->name,
                'status' => $p->status->label(),
            ])
            ->values();

        return inertia('accreditation/applications/create', [
            'programs' => $programs,
            'application_types' => array_map(
                fn (ApplicationType $t): array => ['value' => $t->value, 'label' => $t->label()],
                ApplicationType::cases(),
            ),
        ]);
    }

    /**
     * Persist a new draft immediately and redirect into the wizard.
     */
    public function store(StoreApplicationRequest $request): RedirectResponse
    {
        try {
            $application = $this->wizard->createDraft(
                $request->user(),
                $request->programOrFail(),
                $request->applicationType(),
            );
        } catch (ApplicationSubmissionException $e) {
            throw ValidationException::withMessages([
                $e->field() => $e->getMessage(),
            ]);
        }

        return redirect()
            ->route('applications.wizard.edit', $application)
            ->with('success', 'Draft application '.$application->application_reference.' created.');
    }

    /**
     * Withdraw an unsubmitted draft.
     */
    public function withdraw(Request $request, AccreditationApplication $application): RedirectResponse
    {
        $this->authorize('withdraw', $application);

        try {
            $this->wizard->withdrawDraft($request->user(), $application);
        } catch (ApplicationSubmissionException $e) {
            return back()->withErrors([$e->field() => $e->getMessage()]);
        }

        return redirect()
            ->route('applications.index')
            ->with('success', 'Draft '.$application->application_reference.' was withdrawn.');
    }

    /**
     * Applicant resubmits a returned application.
     */
    public function resubmit(Request $request, AccreditationApplication $application): RedirectResponse
    {
        $this->authorize('resubmitCompletenessReview', $application);

        try {
            $this->reviews->resubmit($request->user(), $application);
        } catch (CompletenessReviewException $e) {
            return back()->withErrors([$e->field() => $e->getMessage()]);
        }

        return back()->with('success', 'Application '.$application->application_reference.' was resubmitted.');
    }
}
