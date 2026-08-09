<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\ApplicationStatus;
use App\Exceptions\CompletenessReviewException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accreditation\ReturnForRevisionRequest;
use App\Models\AccreditationApplication;
use App\Services\Accreditation\ApplicationCompletenessService;
use App\Services\Accreditation\CompletenessReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

/**
 * Reviewer-facing completeness review.
 *
 * Completeness review is an administrative and documentary check only: these
 * actions never create an accreditation decision. Every action re-checks the
 * policy, so a guessed URL id cannot move another institution's application.
 */
class ApplicationCompletenessController extends Controller
{
    public function __construct(
        private readonly CompletenessReviewService $reviews,
        private readonly ApplicationCompletenessService $completeness,
    ) {}

    /**
     * Reviewer dashboard: applications awaiting or under completeness review.
     */
    public function dashboard(Request $request): Response
    {
        $this->authorize('viewCompletenessDashboard', AccreditationApplication::class);

        $applications = AccreditationApplication::query()
            ->with(['institution', 'program', 'submitter'])
            ->whereIn('status', [
                ApplicationStatus::Submitted,
                ApplicationStatus::UnderCompletenessReview,
                ApplicationStatus::ReturnedForRevision,
            ])
            ->orderByDesc('updated_at')
            ->get();

        return inertia('accreditation/reviewer/completeness/dashboard', [
            'applications' => $applications->map(fn (AccreditationApplication $app): array => [
                'id' => $app->id,
                'application_reference' => $app->application_reference,
                'status' => $app->status->value,
                'status_label' => $app->status->label(),
                'institution' => $app->institution?->name,
                'program' => $app->program?->name,
                'submitted_at' => $app->submitted_at?->toDayDateTimeString(),
                'returned_comment' => $app->returned_comment,
                'is_complete' => $this->completeness->isComplete($app),
            ]),
        ]);
    }

    /**
     * Reviewer completeness detail page: calculation, deficiency list, history.
     */
    public function show(Request $request, AccreditationApplication $application): Response
    {
        $this->authorize('view', $application);

        $application->load([
            'institution',
            'program',
            'submitter',
            'underReviewBy',
            'returnedBy',
            'completedForInspectionBy',
            'completenessReviews.reviewer',
        ]);

        $summary = $this->completeness->summary($application);

        return inertia('accreditation/reviewer/completeness/show', [
            'application' => [
                'id' => $application->id,
                'application_reference' => $application->application_reference,
                'status' => $application->status->value,
                'status_label' => $application->status->label(),
                'institution' => $application->institution?->name,
                'program' => $application->program?->name,
                'submitted_at' => $application->submitted_at?->toDayDateTimeString(),
                'under_review_by' => $application->underReviewBy?->name,
                'under_review_at' => $application->under_review_at?->toDayDateTimeString(),
                'returned_by' => $application->returnedBy?->name,
                'returned_at' => $application->returned_at?->toDayDateTimeString(),
                'returned_comment' => $application->returned_comment,
                'completed_for_inspection_by' => $application->completedForInspectionBy?->name,
                'completed_for_inspection_at' => $application->completed_for_inspection_at?->toDayDateTimeString(),
            ],
            'summary' => $summary,
            'history' => $application->completenessReviews->map(fn ($row): array => [
                'id' => $row->id,
                'decision' => $row->decision,
                'reviewer' => $row->reviewer?->name,
                'comment' => $row->reviewer_comment,
                'created_at' => $row->created_at?->toDayDateTimeString(),
            ]),
            'can_begin' => $request->user()->can('beginCompletenessReview', $application),
            'can_return' => $request->user()->can('returnForRevision', $application),
            'can_complete' => $request->user()->can('completeCompletenessReview', $application),
        ]);
    }

    public function beginReview(Request $request, AccreditationApplication $application): RedirectResponse
    {
        $this->authorize('beginCompletenessReview', $application);

        try {
            $this->reviews->beginReview($request->user(), $application);
        } catch (CompletenessReviewException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return back()->with('success', 'Completeness review started.');
    }

    public function returnForRevision(ReturnForRevisionRequest $request, AccreditationApplication $application): RedirectResponse
    {
        $this->authorize('returnForRevision', $application);

        try {
            $this->reviews->returnForRevision(
                $request->user(),
                $application,
                $request->input('comment'),
            );
        } catch (CompletenessReviewException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return back()->with('success', 'Application returned for revision.');
    }

    public function markComplete(Request $request, AccreditationApplication $application): RedirectResponse
    {
        $this->authorize('completeCompletenessReview', $application);

        try {
            $this->reviews->markComplete($request->user(), $application);
        } catch (CompletenessReviewException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return back()->with('success', 'Application marked complete for inspection.');
    }
}
