<?php

namespace App\Services\Accreditation;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\FindingReviewAction;
use App\Enums\FindingStatus;
use App\Exceptions\FindingException;
use App\Models\AccreditationApplication;
use App\Models\Finding;
use App\Models\FindingReview;
use App\Models\Inspection;
use App\Models\InspectionCriterion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Orchestrates the Finding lifecycle.
 *
 * Findings are created only from an inspection with immutable criteria that
 * carry a recorded result. The finding copies and preserves the historical
 * context so later checklist/inspection edits cannot change it. Status
 * transitions are enforced here (server-side) so the client cannot bypass them.
 *
 * Transitions:
 *   DRAFT                         -> OPEN            (reviewer publishes)
 *   OPEN                          -> ACKNOWLEDGED    (institution)
 *   OPEN                          -> CORRECTIVE_ACTION_REQUIRED
 *   ACKNOWLEDGED                  -> CORRECTIVE_ACTION_REQUIRED
 *   CORRECTIVE_ACTION_REQUIRED    -> UNDER_REVIEW    (institution submits a CA)
 *   UNDER_REVIEW                  -> RESOLVED        (reviewer accepts a CA)
 *   RESOLVED                      -> CLOSED          (reviewer, with summary)
 *   RESOLVED                      -> REOPENED        (reviewer, with reason)
 *   UNDER_REVIEW                  -> REOPENED        (reviewer, with reason)
 *   OPEN                          -> WAIVED          (authorized PSP/CART, reason)
 *   any non-closed                -> VOIDED          (authorized admin, reason)
 *
 * A finding cannot be CLOSED without an accepted corrective action or an
 * authorized waiver, and CLOSED requires a closing summary. REOPENED requires a
 * reason. VOIDED/WAIVED require a reason and the right role. Evidence upload
 * never auto-closes a finding.
 *
 * This module never creates an accreditation decision; it only records the
 * corrective-action workflow and its full history, and advances the application
 * through the findings-related statuses (FINDINGS_ISSUED,
 * CORRECTIVE_ACTION_REQUIRED, FOR_FINAL_EVALUATION) without ever approving it.
 */
class FindingService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly CorrectiveActionService $correctiveActions,
    ) {}

    /**
     * Whether a finding may be created from this inspection/criterion.
     *
     * Validates the inspection's lifecycle state and that the criterion belongs
     * to it. The criterion's recorded-result requirement is enforced separately
     * in {@see requireCreatable} so the failure reason is specific.
     */
    public function isCreatable(Inspection $inspection, ?InspectionCriterion $criterion = null): bool
    {
        if ($criterion !== null && $criterion->inspection_id !== $inspection->id) {
            return false;
        }

        $completionAllowed = (bool) config('findings.allow_before_completion', true);

        return $inspection->status->value === 'completed'
            || ($completionAllowed && $inspection->status->value === 'submitted_for_review');
    }

    public function requireCreatable(Inspection $inspection, ?InspectionCriterion $criterion = null): void
    {
        if (! $this->isCreatable($inspection, $criterion)) {
            throw FindingException::notCreatable();
        }

        if ($criterion !== null && ($criterion->result === null || $criterion->result->value === 'not_started')) {
            throw FindingException::criterionRequired();
        }
    }

    /**
     * Create a finding from an inspection criterion (or the inspection directly).
     *
     * Copies the historical context snapshot and a unique finding reference, then
     * records the initial review record and audit entry in a transaction.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $reviewer, Inspection $inspection, array $data, ?InspectionCriterion $criterion = null): Finding
    {
        if (! $reviewer->canReviewInspection()) {
            throw FindingException::createAuthorization();
        }

        $this->requireCreatable($inspection, $criterion);

        $institutionId = $inspection->application->institution_id;

        $finding = DB::transaction(function () use ($reviewer, $inspection, $data, $criterion, $institutionId): Finding {
            $finding = Finding::create([
                'inspection_id' => $inspection->id,
                'inspection_criterion_id' => $criterion?->id,
                'application_id' => $inspection->application_id,
                'finding_reference' => $this->nextReference($inspection),
                'category' => $data['category'],
                'severity' => $criterion !== null ? $criterion->severity : ($data['severity'] ?? 'unspecified'),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'source_result' => $criterion?->result?->value,
                'source_section_name' => $criterion?->section_name,
                'source_requirement_code' => $criterion?->requirement_code,
                'source_requirement_title' => $criterion?->title,
                'source_requirement_description' => $criterion?->description,
                'source_inspector_notes' => $criterion?->inspector_notes,
                'source_institution_response' => $criterion?->institution_response,
                'status' => FindingStatus::Draft,
                'due_date' => $data['due_date'] ?? null,
                'created_by' => $reviewer->id,
            ]);

            $this->audit->log(
                AuditAction::FindingCreated,
                $reviewer,
                $finding,
                $institutionId,
                [
                    'inspection_id' => $inspection->id,
                    'inspection_criterion_id' => $criterion?->id,
                    'application_id' => $inspection->application_id,
                    'category' => $data['category'],
                    'source_result' => $criterion?->result?->value,
                ],
            );

            FindingReview::create([
                'finding_id' => $finding->id,
                'corrective_action_id' => null,
                'reviewer_id' => $reviewer->id,
                'action' => FindingReviewAction::Created,
                'status_before' => null,
                'status_after' => FindingStatus::Draft->value,
                'comment' => 'Finding created.',
            ]);

            return $finding;
        });

        // Extension point: a notifications subsystem would hook in here
        // (findings.notifications_enabled is false by default).

        return $finding->refresh();
    }

    /**
     * Publish a draft finding as OPEN.
     */
    public function open(User $actor, Finding $finding): Finding
    {
        $this->assertReviewer($actor);
        $this->assertTransition($finding, FindingStatus::Open);

        $finding = $this->transition($actor, $finding, FindingStatus::Open, [], AuditAction::FindingOpened, FindingReviewAction::Opened, 'Finding published.');

        // Publishing a finding advances the application workflow (never to an
        // approved state).
        $this->advanceApplicationOnPublish($finding);

        return $finding->refresh();
    }

    /**
     * Institution acknowledges the finding.
     */
    public function acknowledge(User $actor, Finding $finding): Finding
    {
        if (! $this->isInstitutionMember($actor, $finding)) {
            throw FindingException::institutionAuthorization();
        }

        $this->assertTransition($finding, FindingStatus::Acknowledged);

        return DB::transaction(function () use ($actor, $finding): Finding {
            $finding->forceFill([
                'institution_acknowledged_at' => now(),
                'acknowledged_by' => $actor->id,
            ])->save();

            return $this->transition($actor, $finding, FindingStatus::Acknowledged, [], AuditAction::FindingAcknowledged, FindingReviewAction::Acknowledged, 'Finding acknowledged by institution.');
        });
    }

    /**
     * Classify the finding as requiring a corrective action (reviewer moves it
     * past acknowledgement into the corrective-action phase), optionally creating
     * the first corrective action.
     *
     * @param  array<string, mixed>|null  $actionData
     */
    public function requireCorrectiveAction(User $actor, Finding $finding, ?array $actionData = null): Finding
    {
        $this->assertReviewer($actor);

        if (! in_array($finding->status->value, ['open', 'acknowledged'], true)) {
            throw FindingException::invalidTransition($finding->status->value, 'corrective_action_required');
        }

        $finding = $this->transition($actor, $finding, FindingStatus::CorrectiveActionRequired, [], AuditAction::FindingReturned, FindingReviewAction::Returned, 'Corrective action required.');

        if ($actionData !== null) {
            $this->correctiveActions->create($actor, $finding, $actionData);
        }

        return $finding->refresh();
    }

    /**
     * Mark the finding resolved (reviewer) once a corrective action is accepted.
     */
    public function resolve(User $actor, Finding $finding): Finding
    {
        $this->assertReviewer($actor);

        if ($finding->status !== FindingStatus::UnderReview) {
            throw FindingException::invalidTransition($finding->status->value, 'resolved');
        }

        if (! $finding->hasAcceptedCorrectiveAction()) {
            throw FindingException::notClosable();
        }

        $finding = $this->transition($actor, $finding, FindingStatus::Resolved, ['resolved_at' => now()], AuditAction::FindingResolved, FindingReviewAction::Accepted, 'Finding resolved.');

        $this->syncApplicationStatus($finding->application);

        return $finding->refresh();
    }

    /**
     * Close a resolved finding. Requires a closing summary and an accepted
     * corrective action (or a prior authorized waiver).
     */
    public function close(User $actor, Finding $finding, string $summary): Finding
    {
        $this->assertReviewer($actor);

        if ($finding->status !== FindingStatus::Resolved) {
            throw FindingException::invalidTransition($finding->status->value, 'closed');
        }

        if (! $finding->hasAcceptedCorrectiveAction() && ! $finding->status->isWaived()) {
            throw FindingException::notClosable();
        }

        if (blank($summary)) {
            throw FindingException::closingSummaryRequired();
        }

        $finding = $this->transition(
            $actor,
            $finding,
            FindingStatus::Closed,
            ['closed_at' => now(), 'closed_by' => $actor->id],
            AuditAction::FindingClosed,
            FindingReviewAction::Closed,
            $summary,
        );

        $this->syncApplicationStatus($finding->application);

        return $finding->refresh();
    }

    /**
     * Reopen a resolved/under-review finding with a required reason.
     */
    public function reopen(User $actor, Finding $finding, string $reason): Finding
    {
        $this->assertReviewer($actor);

        if (! in_array($finding->status->value, ['under_review', 'resolved', 'closed'], true)) {
            throw FindingException::invalidTransition($finding->status->value, 'reopened');
        }

        if (blank($reason)) {
            throw FindingException::reopenReasonRequired();
        }

        return $this->transition($actor, $finding, FindingStatus::Reopened, [], AuditAction::FindingReopened, FindingReviewAction::Reopened, $reason);
    }

    /**
     * Waive an open finding (authorized PSP/CART only, with a reason).
     */
    public function waive(User $actor, Finding $finding, string $reason): Finding
    {
        if (! $actor->canReviewInspection()) {
            throw FindingException::waiverAuthorization();
        }

        if ($finding->status !== FindingStatus::Open) {
            throw FindingException::invalidTransition($finding->status->value, 'waived');
        }

        if (blank($reason)) {
            throw FindingException::waiverRequiresReason();
        }

        $finding = $this->transition($actor, $finding, FindingStatus::Waived, ['waived_at' => now(), 'waived_by' => $actor->id, 'void_reason' => $reason], AuditAction::FindingWaived, FindingReviewAction::Waived, $reason);

        $this->syncApplicationStatus($finding->application);

        return $finding->refresh();
    }

    /**
     * Void any non-closed finding (authorized administrator, with a reason).
     */
    public function void(User $actor, Finding $finding, string $reason): Finding
    {
        if (! $actor->canReviewInspection()) {
            throw FindingException::voidAuthorization();
        }

        if ($finding->status->isClosed()) {
            throw FindingException::cannotVoidClosed();
        }

        if (blank($reason)) {
            throw FindingException::voidRequiresReason();
        }

        $finding = $this->transition($actor, $finding, FindingStatus::Voided, ['voided_at' => now(), 'voided_by' => $actor->id, 'void_reason' => $reason], AuditAction::FindingVoided, FindingReviewAction::Voided, $reason);

        $this->syncApplicationStatus($finding->application);

        return $finding->refresh();
    }

    /**
     * Move a CorrectiveActionRequired finding into UNDER_REVIEW when the
     * institution submits a corrective-action response.
     *
     * Called by the CorrectiveActionService so the finding/finding-review/audit
     * bookkeeping stays in one place.
     */
    public function markUnderReviewFromSubmission(User $actor, Finding $finding): void
    {
        if ($finding->status !== FindingStatus::CorrectiveActionRequired) {
            return;
        }

        $this->transition($actor, $finding, FindingStatus::UnderReview, [], AuditAction::FindingSubmitted, FindingReviewAction::Submitted, 'Corrective action submitted for review.');
    }

    /**
     * Move an UNDER_REVIEW finding to RESOLVED when a corrective action is
     * accepted by the reviewer.
     *
     * Called by the CorrectiveActionService.
     */
    public function markResolvedFromAcceptance(User $actor, Finding $finding): void
    {
        if ($finding->status !== FindingStatus::UnderReview) {
            return;
        }

        $this->transition($actor, $finding, FindingStatus::Resolved, ['resolved_at' => now()], AuditAction::FindingResolved, FindingReviewAction::Accepted, 'Corrective action accepted; finding resolved.');

        $this->syncApplicationStatus($finding->application);
    }

    /**
     * Return an UNDER_REVIEW finding to CORRECTIVE_ACTION_REQUIRED when a
     * submitted corrective action is rejected.
     *
     * Called by the CorrectiveActionService.
     */
    public function markReturnedFromRejection(User $actor, Finding $finding): void
    {
        if ($finding->status !== FindingStatus::UnderReview) {
            return;
        }

        $this->transition($actor, $finding, FindingStatus::CorrectiveActionRequired, [], AuditAction::FindingRejected, FindingReviewAction::Rejected, 'Corrective action rejected; returned for revision.');
    }

    /**
     * Build a unique finding reference scoped to the inspection's application.
     */
    private function nextReference(Inspection $inspection): string
    {
        $prefix = 'FND-'.($inspection->application->application_reference ?? $inspection->application_id).'-';

        do {
            $candidate = $prefix.Str::upper(Str::random(6));
        } while (Finding::where('finding_reference', $candidate)->exists());

        return $candidate;
    }

    private function assertReviewer(User $actor): void
    {
        if (! $actor->canReviewInspection()) {
            throw FindingException::reviewAuthorization();
        }
    }

    private function isInstitutionMember(User $actor, Finding $finding): bool
    {
        return $actor->belongsToInstitution($finding->institutionId());
    }

    /**
     * @throws FindingException
     */
    private function assertTransition(Finding $finding, FindingStatus $to): void
    {
        if (! $finding->status->canTransitionTo($to)) {
            throw FindingException::invalidTransition($finding->status->value, $to->value);
        }
    }

    /**
     * @param  array<string, mixed>  $columnUpdates
     */
    private function transition(
        User $actor,
        Finding $finding,
        FindingStatus $to,
        array $columnUpdates,
        AuditAction $action,
        FindingReviewAction $reviewAction,
        string $comment = '',
    ): Finding {
        $from = $finding->status;
        $institutionId = $finding->institutionId();

        return DB::transaction(function () use ($actor, $finding, $to, $columnUpdates, $action, $reviewAction, $comment, $from, $institutionId): Finding {
            $finding->forceFill(array_merge(['status' => $to], $columnUpdates))->save();

            $this->audit->log(
                $action,
                $actor,
                $finding,
                $institutionId,
                [
                    'finding_id' => $finding->id,
                    'finding_reference' => $finding->finding_reference,
                    'from_status' => $from?->value,
                    'to_status' => $to->value,
                    'comment' => $comment,
                ],
            );

            FindingReview::create([
                'finding_id' => $finding->id,
                'corrective_action_id' => null,
                'reviewer_id' => $actor->id,
                'action' => $reviewAction,
                'status_before' => $from?->value,
                'status_after' => $to->value,
                'comment' => $comment,
            ]);

            return $finding->refresh();
        });
    }

    /**
     * On publishing a finding, the application moves to FINDINGS_ISSUED (or
     * CORRECTIVE_ACTION_REQUIRED if a corrective action is immediately required).
     * This never approves the application.
     */
    private function advanceApplicationOnPublish(Finding $finding): void
    {
        $application = $finding->application;

        if (! in_array($application->status->value, [ApplicationStatus::FindingsIssued->value, ApplicationStatus::CorrectiveActionRequired->value, ApplicationStatus::ForFinalEvaluation->value], true)) {
            $target = $application->status->value === ApplicationStatus::InspectionCompleted->value
                ? ApplicationStatus::FindingsIssued
                : ApplicationStatus::FindingsIssued;

            $application->update(['status' => $target]);

            $this->audit->log(
                AuditAction::StatusChanged,
                null,
                $application,
                $application->institution_id,
                [
                    'from_status' => ApplicationStatus::InspectionCompleted->value,
                    'to_status' => $target->value,
                    'reason' => 'Finding published.',
                ],
            );
        }
    }

    /**
     * Recompute the application's findings-related status. When every finding is
     * resolved/closed/waived/voided the application is flagged FOR_FINAL_EVALUATION
     * (a soft indicator, never an approval).
     */
    private function syncApplicationStatus(AccreditationApplication $application): void
    {
        $open = $application->findings()
            ->whereNotIn('status', ['resolved', 'closed', 'waived', 'voided'])
            ->exists();

        if ($open) {
            return;
        }

        if ($application->findings()->exists()
            && ! in_array($application->status->value, [ApplicationStatus::ForFinalEvaluation->value, ApplicationStatus::Approved->value], true)
        ) {
            $application->update(['status' => ApplicationStatus::ForFinalEvaluation]);

            $this->audit->log(
                AuditAction::StatusChanged,
                null,
                $application,
                $application->institution_id,
                [
                    'to_status' => ApplicationStatus::ForFinalEvaluation->value,
                    'reason' => 'All findings resolved; ready for final evaluation.',
                ],
            );
        }
    }
}
