<?php

namespace App\Services\Accreditation;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Exceptions\CompletenessReviewException;
use App\Models\AccreditationApplication;
use App\Models\CompletenessReview;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the completeness-review workflow.
 *
 * Completeness review is an administrative and documentary check only. These
 * transitions never create an accreditation decision, never determine final
 * compliance, and never schedule an inspection. Every status change is audit
 * logged and recorded in the append-only completeness_reviews history.
 *
 * The transition matrix is enforced here (server-side) so the client cannot
 * bypass it:
 *   SUBMITTED                    -> UNDER_COMPLETENESS_REVIEW
 *   UNDER_COMPLETENESS_REVIEW    -> RETURNED_FOR_REVISION   (comment required)
 *   UNDER_COMPLETENESS_REVIEW    -> COMPLETE_FOR_INSPECTION  (no blocking items)
 *   RETURNED_FOR_REVISION        -> SUBMITTED               (applicant resubmit)
 */
class CompletenessReviewService
{
    public function __construct(
        private readonly ApplicationCompletenessService $completeness,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Reviewer begins the completeness review.
     *
     * @throws CompletenessReviewException
     */
    public function beginReview(User $reviewer, AccreditationApplication $application): AccreditationApplication
    {
        $this->assertReviewer($reviewer);

        if ($application->status !== ApplicationStatus::Submitted) {
            throw CompletenessReviewException::notSubmittable();
        }

        return $this->transition(
            $reviewer,
            $application,
            ApplicationStatus::UnderCompletenessReview,
            'under_review',
            'Completeness review started.',
            fn (AccreditationApplication $app): array => [
                'under_review_at' => now(),
                'under_review_by' => $reviewer->id,
            ],
            AuditAction::CompletenessReviewStarted,
        );
    }

    /**
     * Reviewer returns the application for revision. A comment is mandatory.
     *
     * @throws CompletenessReviewException
     */
    public function returnForRevision(User $reviewer, AccreditationApplication $application, string $comment): AccreditationApplication
    {
        $this->assertReviewer($reviewer);

        if ($application->status !== ApplicationStatus::UnderCompletenessReview) {
            throw CompletenessReviewException::notUnderReview();
        }

        if (blank($comment)) {
            throw CompletenessReviewException::commentRequired();
        }

        $deficiencies = $this->completeness->deficiencies($application);

        return $this->transition(
            $reviewer,
            $application,
            ApplicationStatus::ReturnedForRevision,
            'returned_for_revision',
            $comment,
            fn (AccreditationApplication $app): array => [
                'returned_at' => now(),
                'returned_by' => $reviewer->id,
                'returned_comment' => $comment,
            ],
            AuditAction::CompletenessReviewReturned,
            $deficiencies,
        );
    }

    /**
     * Reviewer marks the application complete for inspection.
     *
     * Blocked while any deficiency remains (outstanding mandatory checklist
     * response, or a required document missing/rejected/replacement-requested).
     *
     * @throws CompletenessReviewException
     */
    public function markComplete(User $reviewer, AccreditationApplication $application): AccreditationApplication
    {
        $this->assertReviewer($reviewer);

        if ($application->status !== ApplicationStatus::UnderCompletenessReview) {
            throw CompletenessReviewException::notUnderReview();
        }

        $deficiencies = $this->completeness->deficiencies($application);

        if ($deficiencies !== []) {
            throw CompletenessReviewException::blockingItems(count($deficiencies));
        }

        // Extension point: a future inspection-scheduling milestone would be
        // triggered here once the application is complete for inspection. The
        // scheduling subsystem is intentionally not implemented in this milestone.
        return $this->transition(
            $reviewer,
            $application,
            ApplicationStatus::CompleteForInspection,
            'complete_for_inspection',
            'Application marked complete for inspection.',
            fn (AccreditationApplication $app): array => [
                'completed_for_inspection_at' => now(),
                'completed_for_inspection_by' => $reviewer->id,
            ],
            AuditAction::CompletenessReviewComplete,
            $deficiencies,
        );
    }

    /**
     * Applicant resubmits a returned application.
     *
     * @throws CompletenessReviewException
     */
    public function resubmit(User $applicant, AccreditationApplication $application): AccreditationApplication
    {
        if ($application->status !== ApplicationStatus::ReturnedForRevision) {
            throw CompletenessReviewException::notReturned();
        }

        return DB::transaction(function () use ($applicant, $application): AccreditationApplication {
            $application->forceFill([
                'status' => ApplicationStatus::Submitted,
                'returned_at' => null,
                'returned_by' => null,
                'returned_comment' => null,
                'submitted_at' => now(),
                'submitted_by' => $applicant->id,
            ])->save();

            $this->recordReview($application, null, 'resubmitted', null, null);

            $this->audit->log(
                AuditAction::ApplicationResubmitted,
                $applicant,
                $application,
                $application->institution_id,
                [
                    'application_reference' => $application->application_reference,
                    'from_status' => ApplicationStatus::ReturnedForRevision->value,
                    'to_status' => ApplicationStatus::Submitted->value,
                ],
            );

            return $application->refresh();
        });
    }

    /**
     * @param  callable(AccreditationApplication): array<string, mixed>  $columnUpdates
     * @param  array<int, array<string, string>>|null  $deficiencies
     */
    private function transition(
        User $reviewer,
        AccreditationApplication $application,
        ApplicationStatus $to,
        string $decision,
        string $comment,
        callable $columnUpdates,
        AuditAction $action,
        ?array $deficiencies = null,
    ): AccreditationApplication {
        $from = $application->status;

        return DB::transaction(function () use ($reviewer, $application, $to, $decision, $comment, $columnUpdates, $action, $deficiencies, $from): AccreditationApplication {
            $application->forceFill(array_merge(
                ['status' => $to],
                $columnUpdates($application),
            ))->save();

            $this->recordReview($application, $reviewer, $decision, $comment, $deficiencies);

            $this->audit->log(
                $action,
                $reviewer,
                $application,
                $application->institution_id,
                [
                    'application_reference' => $application->application_reference,
                    'from_status' => $from->value,
                    'to_status' => $to->value,
                    'comment' => $comment,
                ],
            );

            return $application->refresh();
        });
    }

    /**
     * Append a completeness-review history row (the completeness report source).
     *
     * @param  array<int, array<string, string>>|null  $deficiencies
     */
    private function recordReview(
        AccreditationApplication $application,
        ?User $reviewer,
        string $decision,
        ?string $comment,
        ?array $deficiencies,
    ): CompletenessReview {
        return CompletenessReview::create([
            'application_id' => $application->id,
            'reviewer_id' => $reviewer?->id,
            'decision' => $decision,
            'reviewer_comment' => $comment,
            'deficiencies' => $deficiencies,
        ]);
    }

    /**
     * Only authorized PSP/CART reviewers drive the completeness review.
     */
    private function assertReviewer(User $reviewer): void
    {
        if (! $reviewer->canReviewCompleteness()) {
            throw CompletenessReviewException::reviewerRequired();
        }
    }
}
