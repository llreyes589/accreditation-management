<?php

namespace App\Services\Accreditation;

use App\Enums\AuditAction;
use App\Enums\CorrectiveActionStatus;
use App\Enums\FindingReviewAction;
use App\Enums\FindingStatus;
use App\Exceptions\FindingException;
use App\Models\CorrectiveAction;
use App\Models\CorrectiveActionEvidence;
use App\Models\Document;
use App\Models\Finding;
use App\Models\FindingReview;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Orchestrates a corrective action's lifecycle.
 *
 * Institution members create/assign, submit responses with evidence (immutable
 * versions), and the reviewer accepts, rejects (reason required), or reopens
 * (reason required). Submitting evidence never auto-closes a corrective action.
 *
 * Transitions:
 *   DRAFT -> OPEN
 *   OPEN -> IN_PROGRESS                 (institution begins)
 *   OPEN -> SUBMITTED                  (institution submits)
 *   IN_PROGRESS -> SUBMITTED
 *   SUBMITTED -> UNDER_REVIEW           (reviewer picks up)
 *   UNDER_REVIEW -> ACCEPTED            (verified)
 *   UNDER_REVIEW -> REJECTED           (returned for correction, reason required)
 *   REJECTED -> IN_PROGRESS
 *   REJECTED -> SUBMITTED
 *   OPEN/IN_PROGRESS -> WITHDRAWN
 *   ACCEPTED -> CLOSED
 *
 * Evidence reuses the existing private document system: each linked file is
 * recorded in corrective_action_evidence and is authorized through DocumentPolicy.
 * OVERDUE is a derived flag (due date passed, not resolved), recomputed from
 * due_date; it is never a terminal state.
 */
class CorrectiveActionService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Create a corrective action against a finding (reviewer or institution).
     *
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, Finding $finding, array $data): CorrectiveAction
    {
        if ($finding->status->isClosed() || $finding->status->isVoided()) {
            throw FindingException::notEditable();
        }

        if (blank($data['title'] ?? null)) {
            throw FindingException::responseRequired();
        }

        if (empty($data['responsible_user_id']) && empty($data['responsible_role'])) {
            throw FindingException::assignedUserRequired();
        }

        if (empty($data['due_date'])) {
            throw FindingException::dueDateRequired();
        }

        return DB::transaction(function () use ($actor, $finding, $data): CorrectiveAction {
            $action = CorrectiveAction::create([
                'finding_id' => $finding->id,
                'action_reference' => $this->nextReference($finding),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'responsible_user_id' => $data['responsible_user_id'] ?? null,
                'responsible_role' => $data['responsible_role'] ?? null,
                'due_date' => $data['due_date'],
                'status' => CorrectiveActionStatus::Open,
                'created_by' => $actor->id,
            ]);

            $this->audit->log(
                AuditAction::CorrectiveActionCreated,
                $actor,
                $action,
                $finding->institutionId(),
                [
                    'finding_id' => $finding->id,
                    'action_reference' => $action->action_reference,
                    'responsible_user_id' => $data['responsible_user_id'] ?? null,
                    'due_date' => $data['due_date'],
                ],
            );

            return $action->refresh();
        });
    }

    /**
     * Institution marks the action in progress.
     */
    public function start(User $institutionUser, CorrectiveAction $action): CorrectiveAction
    {
        $this->assertInstitution($institutionUser, $action);
        $this->assertTransition($action, CorrectiveActionStatus::InProgress);

        return $this->transition($institutionUser, $action, CorrectiveActionStatus::InProgress, [], 'Corrective action started.');
    }

    /**
     * Institution submits a response: proposed action, implementation notes, and
     * referenced evidence. Appended as an immutable version; previous submissions
     * are never overwritten. Submitting the first corrective action moves the
     * finding into UNDER_REVIEW.
     *
     * @param  array{proposed_action: string, implementation_notes?: string|null, evidence?: array<int, array{document_id: int, note?: string}>}  $data
     */
    public function submit(User $institutionUser, CorrectiveAction $action, array $data): CorrectiveAction
    {
        $this->assertInstitution($institutionUser, $action);

        if (! in_array($action->status->value, ['open', 'in_progress', 'rejected'], true)) {
            throw FindingException::notSubmittable();
        }

        if (blank($data['proposed_action'])) {
            throw FindingException::responseRequired();
        }

        $evidence = $data['evidence'] ?? [];
        $this->assertEvidenceAuthorized($institutionUser, $action, $evidence);

        $versionNumber = ($action->versions()->max('version_number') ?? 0) + 1;

        return DB::transaction(function () use ($institutionUser, $action, $data, $evidence, $versionNumber): CorrectiveAction {
            $action->forceFill([
                'status' => CorrectiveActionStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $institutionUser->id,
            ])->save();

            $action->versions()->create([
                'version_number' => $versionNumber,
                'proposed_action' => $data['proposed_action'],
                'implementation_notes' => $data['implementation_notes'] ?? null,
                'evidence' => $evidence,
                'submitted_by' => $institutionUser->id,
                'submitted_at' => now(),
            ]);

            // Link evidence to the private document system (reused authorization).
            foreach ($evidence as $item) {
                CorrectiveActionEvidence::create([
                    'corrective_action_id' => $action->id,
                    'document_id' => $item['document_id'],
                    'description' => $item['note'] ?? null,
                    'uploaded_by' => $institutionUser->id,
                ]);
            }

            $this->audit->log(
                AuditAction::CorrectiveActionSubmitted,
                $institutionUser,
                $action,
                $action->finding->institutionId(),
                [
                    'finding_id' => $action->finding_id,
                    'action_reference' => $action->action_reference,
                    'version_number' => $versionNumber,
                    'evidence_count' => count($evidence),
                ],
            );

            // The institution submission puts the finding under review.
            $this->advanceFinding($institutionUser, $action->finding, FindingStatus::UnderReview, AuditAction::FindingSubmitted, FindingReviewAction::Submitted, 'Corrective action submitted for review.');

            return $action->refresh();
        });
    }

    /**
     * Reviewer accepts the submitted corrective action (verified). This resolves
     * the finding (when it was under review) but does NOT auto-close it.
     */
    public function accept(User $reviewer, CorrectiveAction $action): CorrectiveAction
    {
        $this->assertReviewer($reviewer);
        $this->assertTransition($action, CorrectiveActionStatus::Accepted);

        return DB::transaction(function () use ($reviewer, $action): CorrectiveAction {
            $action = $this->transition($reviewer, $action, CorrectiveActionStatus::Accepted, ['accepted_at' => now(), 'accepted_by' => $reviewer->id], 'Corrective action accepted.');

            $this->advanceFinding($reviewer, $action->finding, FindingStatus::Resolved, AuditAction::FindingResolved, FindingReviewAction::Accepted, 'Corrective action accepted; finding resolved.');

            return $action->refresh();
        });
    }

    /**
     * Reviewer rejects the submission (reason required). The finding returns to
     * CORRECTIVE_ACTION_REQUIRED so the institution can resubmit.
     */
    public function reject(User $reviewer, CorrectiveAction $action, string $reason): CorrectiveAction
    {
        $this->assertReviewer($reviewer);

        if (! in_array($action->status->value, ['submitted', 'under_review'], true)) {
            throw FindingException::notReviewable();
        }

        if (blank($reason)) {
            throw FindingException::rejectionReasonRequired();
        }

        return DB::transaction(function () use ($reviewer, $action, $reason): CorrectiveAction {
            $action = $this->transition($reviewer, $action, CorrectiveActionStatus::Rejected, ['rejected_at' => now(), 'rejected_by' => $reviewer->id, 'rejection_reason' => $reason], 'Corrective action rejected.');

            $this->advanceFinding($reviewer, $action->finding, FindingStatus::CorrectiveActionRequired, AuditAction::FindingRejected, FindingReviewAction::Rejected, 'Corrective action rejected; returned for revision.');

            return $action->refresh();
        });
    }

    /**
     * Institution withdraws a not-yet-submitted action.
     */
    public function withdraw(User $institutionUser, CorrectiveAction $action): CorrectiveAction
    {
        $this->assertInstitution($institutionUser, $action);

        if (! in_array($action->status->value, ['open', 'in_progress'], true)) {
            throw FindingException::notSubmittable();
        }

        return $this->transition($institutionUser, $action, CorrectiveActionStatus::Withdrawn, [], 'Corrective action withdrawn.');
    }

    /**
     * Close an accepted corrective action.
     */
    public function close(User $reviewer, CorrectiveAction $action): CorrectiveAction
    {
        $this->assertReviewer($reviewer);

        if ($action->status !== CorrectiveActionStatus::Accepted) {
            throw FindingException::invalidTransition($action->status->value, 'closed');
        }

        return $this->transition($reviewer, $action, CorrectiveActionStatus::Closed, ['closed_at' => now(), 'closed_by' => $reviewer->id], 'Corrective action closed.');
    }

    /**
     * Advance the owning finding to a new status, recording a finding review and
     * audit entry. No-op when the finding is not in the expected prior state.
     */
    private function advanceFinding(User $actor, Finding $finding, FindingStatus $to, AuditAction $action, FindingReviewAction $reviewAction, string $comment): void
    {
        if ($finding->status->value === $to->value) {
            return;
        }

        $from = $finding->status->value;

        $finding->forceFill(['status' => $to])->save();

        $this->audit->log(
            $action,
            $actor,
            $finding,
            $finding->institutionId(),
            [
                'finding_id' => $finding->id,
                'finding_reference' => $finding->finding_reference,
                'from_status' => $from,
                'to_status' => $to->value,
                'comment' => $comment,
            ],
        );

        FindingReview::create([
            'finding_id' => $finding->id,
            'corrective_action_id' => null,
            'reviewer_id' => $actor->id,
            'action' => $reviewAction,
            'status_before' => $from,
            'status_after' => $to->value,
            'comment' => $comment,
        ]);
    }

    /**
     * Every linked document must exist, belong to the finding's application, and
     * be viewable by the submitting user (reuses DocumentPolicy authorization).
     *
     * @param  array<int, array{document_id: int, note?: string}>  $evidence
     */
    private function assertEvidenceAuthorized(User $user, CorrectiveAction $action, array $evidence): void
    {
        foreach ($evidence as $item) {
            if (empty($item['document_id'])) {
                continue;
            }

            /** @var Document|null $document */
            $document = Document::find($item['document_id']);

            if ($document === null || $document->application_id !== $action->finding->application_id) {
                throw FindingException::evidenceAuthorization();
            }

            if (! Gate::forUser($user)->check('view', $document)) {
                throw FindingException::evidenceAuthorization();
            }
        }
    }

    private function nextReference(Finding $finding): string
    {
        $prefix = 'CA-'.($finding->finding_reference ?? $finding->id).'-';

        do {
            $candidate = $prefix.Str::upper(Str::random(5));
        } while (CorrectiveAction::where('action_reference', $candidate)->exists());

        return $candidate;
    }

    private function assertReviewer(User $actor): void
    {
        if (! $actor->canReviewInspection()) {
            throw FindingException::reviewAuthorization();
        }
    }

    private function assertInstitution(User $actor, CorrectiveAction $action): void
    {
        if (! $actor->belongsToInstitution($action->finding->institutionId() ?? 0)) {
            throw FindingException::institutionAuthorization();
        }
    }

    /**
     * @throws FindingException
     */
    private function assertTransition(CorrectiveAction $action, CorrectiveActionStatus $to): void
    {
        if (! $action->status->canTransitionTo($to)) {
            throw FindingException::invalidTransition($action->status->value, $to->value);
        }
    }

    /**
     * @param  array<string, mixed>  $columnUpdates
     */
    private function transition(
        User $actor,
        CorrectiveAction $action,
        CorrectiveActionStatus $to,
        array $columnUpdates,
        string $comment = '',
    ): CorrectiveAction {
        $from = $action->status;
        $institutionId = $action->finding->institutionId();

        return DB::transaction(function () use ($actor, $action, $to, $columnUpdates, $comment, $from, $institutionId): CorrectiveAction {
            $action->forceFill(array_merge(['status' => $to], $columnUpdates))->save();

            $this->audit->log(
                $this->actionFor($to),
                $actor,
                $action,
                $institutionId,
                [
                    'finding_id' => $action->finding_id,
                    'action_reference' => $action->action_reference,
                    'from_status' => $from?->value,
                    'to_status' => $to->value,
                    'comment' => $comment,
                ],
            );

            return $action->refresh();
        });
    }

    private function actionFor(CorrectiveActionStatus $to): AuditAction
    {
        return match ($to) {
            CorrectiveActionStatus::InProgress => AuditAction::CorrectiveActionSubmitted,
            CorrectiveActionStatus::Submitted => AuditAction::CorrectiveActionSubmitted,
            CorrectiveActionStatus::Accepted => AuditAction::CorrectiveActionAccepted,
            CorrectiveActionStatus::Rejected => AuditAction::CorrectiveActionRejected,
            CorrectiveActionStatus::Withdrawn => AuditAction::CorrectiveActionWithdrawn,
            CorrectiveActionStatus::Closed => AuditAction::CorrectiveActionClosed,
            default => AuditAction::CorrectiveActionCreated,
        };
    }
}
