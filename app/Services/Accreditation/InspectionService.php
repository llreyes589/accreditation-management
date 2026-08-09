<?php

namespace App\Services\Accreditation;

use App\Enums\AuditAction;
use App\Enums\InspectionResult;
use App\Enums\InspectionStatus;
use App\Exceptions\InspectionException;
use App\Models\AccreditationApplication;
use App\Models\Inspection;
use App\Models\InspectionCriterion;
use App\Models\InspectionEvidence;
use App\Models\InspectionInspector;
use App\Models\InspectionReview;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Orchestrates the inspection-scheduling and checklist workflow.
 *
 * This milestone records evidence and results only — it never creates an
 * accreditation decision, finding, corrective action, or notification. Every
 * status change is audit logged. The transition matrix is enforced here
 * (server-side) so the client cannot bypass it.
 *
 * Transitions:
 *   DRAFT                -> SCHEDULED
 *   SCHEDULED            -> IN_PROGRESS
 *   SCHEDULED            -> RESCHEDULED
 *   SCHEDULED            -> CANCELLED
 *   RESCHEDULED          -> SCHEDULED
 *   IN_PROGRESS          -> SUBMITTED_FOR_REVIEW
 *   SUBMITTED_FOR_REVIEW -> IN_PROGRESS            (returned by reviewer)
 *   SUBMITTED_FOR_REVIEW -> COMPLETED
 *   IN_PROGRESS          -> CANCELLED              (authorized)
 */
class InspectionService
{
    public function __construct(
        private readonly InspectionEligibilityService $eligibility,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Create the inspection, snapshot the checklist, assign inspectors, and mark
     * it SCHEDULED — all within a single transaction. If any step fails the
     * transaction rolls back, leaving no partial criteria or assignments.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $inspectorIds  User ids for the inspector pool.
     * @return array{inspection: Inspection, conflicts: array<int, array<string, mixed>>}
     */
    public function create(
        User $scheduler,
        AccreditationApplication $application,
        array $data,
        int $leadInspectorId,
        array $inspectorIds,
    ): array {
        if (! $scheduler->canScheduleInspection()) {
            throw InspectionException::scheduleAuthorization();
        }

        $this->eligibility->requireEligible($application);

        // Validate the schedule window and required fields.
        $this->validateSchedule($data, $leadInspectorId, $inspectorIds);

        // Detect conflicts before opening the transaction.
        $conflicts = $this->conflicts($inspectorIds, $data['scheduled_start_at'], $data['scheduled_end_at']);

        $inspection = DB::transaction(function () use ($scheduler, $application, $data, $leadInspectorId, $inspectorIds): Inspection {
            $inspection = Inspection::create([
                'application_id' => $application->id,
                'checklist_version_id' => $application->checklist_version_id,
                'status' => InspectionStatus::Scheduled,
                'inspection_type' => $data['inspection_type'] ?? 'onsite',
                'scheduled_start_at' => $data['scheduled_start_at'],
                'scheduled_end_at' => $data['scheduled_end_at'],
                'timezone' => $data['timezone'] ?? 'Asia/Manila',
                'location' => $data['location'] ?? null,
                'meeting_url' => $data['meeting_url'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'created_by' => $scheduler->id,
                'scheduled_by' => $scheduler->id,
            ]);

            $this->snapshotCriteria($inspection, $application);
            $this->assignInspectors($inspection, $leadInspectorId, $inspectorIds);

            $this->audit->log(
                AuditAction::InspectionCreated,
                $scheduler,
                $inspection,
                $application->institution_id,
                [
                    'application_id' => $application->id,
                    'application_reference' => $application->application_reference,
                    'scheduled_start_at' => $inspection->scheduled_start_at?->toDateTimeString(),
                    'inspector_count' => count($inspectorIds),
                ],
            );

            return $inspection;
        });

        // Extension point: an InspectionScheduled event would be dispatched here
        // for the (not-yet-implemented) notification subsystem.

        return ['inspection' => $inspection->refresh(), 'conflicts' => $conflicts];
    }

    /**
     * Copy the application's frozen checklist version requirements into
     * immutable inspection criteria, preserving section/requirement order and the
     * section name, title, description, severity and applicability snapshot.
     */
    private function snapshotCriteria(Inspection $inspection, AccreditationApplication $application): void
    {
        $version = $application->checklistVersion;

        if ($version === null) {
            return;
        }

        $order = 0;

        foreach ($version->sections()->orderBy('position')->get() as $section) {
            foreach ($section->requirements()->orderBy('position')->get() as $requirement) {
                if (! $requirement->is_active) {
                    continue;
                }

                InspectionCriterion::create([
                    'inspection_id' => $inspection->id,
                    'source_requirement_id' => $requirement->id,
                    'section_code' => $section->code,
                    'section_name' => $section->title,
                    'requirement_code' => $requirement->code,
                    'title' => $requirement->requirement,
                    'description' => $requirement->guidance,
                    'severity' => $requirement->severity->value,
                    'display_order' => $order++,
                    'response_type' => $requirement->response_type->value,
                    'result' => InspectionResult::NotStarted,
                ]);
            }
        }
    }

    /**
     * Persist inspector assignments, marking the lead and setting accepted
     * status for the acting lead immediately (an invited inspector who accepts
     * later updates their own row).
     *
     * @param  array<int, int>  $inspectorIds
     */
    private function assignInspectors(Inspection $inspection, int $leadInspectorId, array $inspectorIds): void
    {
        $all = array_values(array_unique(array_merge([$leadInspectorId], $inspectorIds), SORT_NUMERIC));

        foreach ($all as $userId) {
            $isLead = $userId === $leadInspectorId;
            $isSchedulerLead = $isLead && $userId === $inspection->scheduled_by;

            InspectionInspector::create([
                'inspection_id' => $inspection->id,
                'user_id' => $userId,
                'role' => $isLead ? 'lead_inspector' : 'inspector',
                'status' => 'invited',
                'invited_at' => now(),
            ]);
        }

        // When the scheduler is themselves the lead, accept on their behalf.
        if ($isSchedulerLead) {
            $this->acceptAssignment($inspection, $inspection->scheduled_by);
        }
    }

    /**
     * An assigned inspector (or authorized administrator) starts the inspection.
     *
     * @throws InspectionException
     */
    public function start(User $actor, Inspection $inspection): Inspection
    {
        $this->assertCanConduct($actor, $inspection);

        if (! $inspection->status->isScheduled()) {
            throw InspectionException::notStartable();
        }

        return $this->transition($actor, $inspection, InspectionStatus::InProgress, function (Inspection $i): array {
            return ['started_at' => now()];
        }, AuditAction::InspectionStarted, 'Inspection started.');
    }

    /**
     * Save a single criterion change (result, notes, institution response).
     * Does not require the whole checklist to be complete.
     *
     * @param  array<string, mixed>  $changes
     *
     * @throws InspectionException
     */
    public function saveCriterion(User $actor, Inspection $inspection, InspectionCriterion $criterion, array $changes): InspectionCriterion
    {
        $this->assertCanConduct($actor, $inspection);

        if (! $inspection->status->isEditable()) {
            throw InspectionException::notEditable();
        }

        if ($criterion->inspection_id !== $inspection->id) {
            throw InspectionException::notEditable();
        }

        $result = isset($changes['result']) ? InspectionResult::from($changes['result']) : $criterion->result;

        // Validate result-specific rules even on a partial save: a result that
        // requires notes/explanation must carry them, otherwise the save is
        // rejected (the inspector may clear the result to defer).
        if ($result !== InspectionResult::NotStarted && $result !== null) {
            $this->assertResultRules($result, $changes['inspector_notes'] ?? $criterion->inspector_notes);
        }

        return DB::transaction(function () use ($actor, $inspection, $criterion, $changes, $result): InspectionCriterion {
            $criterion->forceFill([
                'result' => $result,
                'inspector_notes' => $changes['inspector_notes'] ?? $criterion->inspector_notes,
                'institution_response' => $changes['institution_response'] ?? $criterion->institution_response,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();

            $this->audit->log(
                AuditAction::CriterionResultChanged,
                $actor,
                $criterion,
                $inspection->application->institution_id,
                [
                    'inspection_id' => $inspection->id,
                    'criterion_id' => $criterion->id,
                    'result' => $result?->value,
                ],
            );

            return $criterion->refresh();
        });
    }

    /**
     * Submit the inspection for review after recalculating completion.
     *
     * @throws InspectionException
     */
    public function submit(User $actor, Inspection $inspection): Inspection
    {
        $this->assertCanConduct($actor, $inspection);

        if ($inspection->status !== InspectionStatus::InProgress) {
            throw InspectionException::notSubmittable();
        }

        $this->assertComplete($inspection);

        return $this->transition($actor, $inspection, InspectionStatus::SubmittedForReview, function (Inspection $i): array {
            return ['submitted_at' => now()];
        }, AuditAction::InspectionSubmitted, 'Inspection submitted for review.');
    }

    /**
     * An authorized reviewer returns a submitted inspection for revision.
     *
     * @throws InspectionException
     */
    public function returnForRevision(User $reviewer, Inspection $inspection, string $comment): Inspection
    {
        $this->assertCanReview($reviewer);

        if ($inspection->status !== InspectionStatus::SubmittedForReview) {
            throw InspectionException::notReturnable();
        }

        if (blank($comment)) {
            throw InspectionException::commentRequired();
        }

        $from = $inspection->status->value;

        $inspection = $this->transition($reviewer, $inspection, InspectionStatus::InProgress, function (Inspection $i): array {
            return ['submitted_at' => null];
        }, AuditAction::InspectionReturned, $comment);

        InspectionReview::create([
            'inspection_id' => $inspection->id,
            'reviewer_id' => $reviewer->id,
            'status_before' => $from,
            'status_after' => InspectionStatus::InProgress->value,
            'comment' => $comment,
        ]);

        return $inspection;
    }

    /**
     * An authorized reviewer completes the inspection.
     *
     * Does NOT create an accreditation decision or a corrective action — it only
     * records the reviewer summary and locks the record. A placeholder for the
     * future FINDINGS_ISSUED extension point is left in the audit context.
     *
     * @throws InspectionException
     */
    public function complete(User $reviewer, Inspection $inspection, string $summary): Inspection
    {
        $this->assertCanReview($reviewer);

        if ($inspection->status !== InspectionStatus::SubmittedForReview) {
            throw InspectionException::notCompletable();
        }

        if (blank($summary)) {
            throw InspectionException::summaryRequired();
        }

        $from = $inspection->status->value;

        $inspection = $this->transition($reviewer, $inspection, InspectionStatus::Completed, function (Inspection $i): array {
            return ['completed_at' => now()];
        }, AuditAction::InspectionCompleted, $summary);

        InspectionReview::create([
            'inspection_id' => $inspection->id,
            'reviewer_id' => $reviewer->id,
            'status_before' => $from,
            'status_after' => InspectionStatus::Completed->value,
            'summary' => $summary,
        ]);

        // Extension point: a FINDINGS_ISSUED status / findings module would hook
        // here once implemented. This milestone intentionally stops at
        // completion; no decision or corrective action is created.

        return $inspection;
    }

    /**
     * Cancel an inspection with a reason (before it is completed).
     *
     * @throws InspectionException
     */
    public function cancel(User $actor, Inspection $inspection, string $reason): Inspection
    {
        $this->assertCanSchedule($actor);

        if ($inspection->status->isTerminal()) {
            throw InspectionException::notCancellable();
        }

        if (blank($reason)) {
            throw InspectionException::cancellationReasonRequired();
        }

        return $this->transition($actor, $inspection, InspectionStatus::Cancelled, function (Inspection $i) use ($reason): array {
            return ['cancelled_at' => now(), 'cancellation_reason' => $reason];
        }, AuditAction::InspectionCancelled, $reason);
    }

    /**
     * Reschedule a scheduled inspection, preserving the prior scheduling history
     * (the previous schedule is retained on the inspection until the new one is
     * committed; the audit log captures the change).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws InspectionException
     */
    public function reschedule(User $actor, Inspection $inspection, array $data): Inspection
    {
        $this->assertCanSchedule($actor);

        if (! $inspection->status->isScheduled()) {
            throw InspectionException::notSchedulable();
        }

        $this->validateSchedule($data, null, []);

        $from = $inspection->status->value;

        $inspection = $this->transition($actor, $inspection, InspectionStatus::Scheduled, function (Inspection $i) use ($data, $actor): array {
            return [
                'scheduled_start_at' => $data['scheduled_start_at'],
                'scheduled_end_at' => $data['scheduled_end_at'],
                'timezone' => $data['timezone'] ?? $i->timezone,
                'location' => $data['location'] ?? $i->location,
                'meeting_url' => $data['meeting_url'] ?? $i->meeting_url,
                'instructions' => $data['instructions'] ?? $i->instructions,
                'scheduled_by' => $actor->id,
            ];
        }, AuditAction::InspectionRescheduled, 'Inspection rescheduled.', ['previous_status' => $from]);

        return $inspection;
    }

    /**
     * Add an inspector to a not-yet-started inspection.
     *
     * @throws InspectionException
     */
    public function addInspector(User $actor, Inspection $inspection, int $userId, string $role = 'inspector'): InspectionInspector
    {
        $this->assertCanSchedule($actor);

        if ($inspection->status->isTerminal()) {
            throw InspectionException::notCancellable();
        }

        if ($inspection->inspectors()->where('user_id', $userId)->exists()) {
            throw InspectionException::sameInspectorTwice();
        }

        return InspectionInspector::create([
            'inspection_id' => $inspection->id,
            'user_id' => $userId,
            'role' => $role,
            'status' => 'invited',
            'invited_at' => now(),
        ]);
    }

    /**
     * Remove an inspector before the inspection begins (history preserved by
     * setting status to removed rather than deleting).
     *
     * @throws InspectionException
     */
    public function removeInspector(User $actor, Inspection $inspection, InspectionInspector $assignment): InspectionInspector
    {
        $this->assertCanSchedule($actor);

        if ($inspection->status->isTerminal() || $inspection->status === InspectionStatus::InProgress) {
            throw InspectionException::notEditable();
        }

        if ($assignment->inspection_id !== $inspection->id) {
            throw InspectionException::notEditable();
        }

        $assignment->forceFill(['status' => 'removed'])->save();

        return $assignment->refresh();
    }

    /**
     * Change the lead inspector before the inspection begins.
     *
     * @throws InspectionException
     */
    public function changeLeadInspector(User $actor, Inspection $inspection, int $userId): void
    {
        $this->assertCanSchedule($actor);

        if ($inspection->status->isTerminal() || $inspection->status === InspectionStatus::InProgress) {
            throw InspectionException::notEditable();
        }

        DB::transaction(function () use ($inspection, $userId): void {
            $inspection->inspectors()
                ->where('role', 'lead_inspector')
                ->update(['role' => 'inspector']);

            $assignment = $inspection->inspectors()->firstOrCreate(
                ['user_id' => $userId, 'inspection_id' => $inspection->id],
                ['status' => 'invited', 'invited_at' => now()],
            );
            $assignment->forceFill(['role' => 'lead_inspector'])->save();
        });
    }

    /**
     * Record an inspector's acceptance of their assignment.
     */
    public function acceptAssignment(Inspection $inspection, int $userId): void
    {
        $assignment = $inspection->inspectors()->where('user_id', $userId)->firstOrFail();
        $assignment->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();
    }

    /**
     * Record an inspector's decline with a reason.
     */
    public function declineAssignment(Inspection $inspection, int $userId, ?string $reason): void
    {
        $assignment = $inspection->inspectors()->where('user_id', $userId)->firstOrFail();
        $assignment->forceFill([
            'status' => 'declined',
            'declined_at' => now(),
            'decline_reason' => $reason,
        ])->save();
    }

    /**
     * Link an existing document as evidence to a criterion. Reuses the secure
     * document model — only a reference is stored, never the file contents.
     *
     * @throws InspectionException
     */
    public function linkEvidence(User $actor, Inspection $inspection, InspectionCriterion $criterion, int $documentId, ?string $note = null): InspectionEvidence
    {
        $this->assertCanConduct($actor, $inspection);

        if (! $inspection->status->isEditable()) {
            throw InspectionException::notEditable();
        }

        if ($criterion->inspection_id !== $inspection->id) {
            throw InspectionException::notEditable();
        }

        return DB::transaction(function () use ($actor, $inspection, $criterion, $documentId, $note): InspectionEvidence {
            $evidence = InspectionEvidence::create([
                'inspection_id' => $inspection->id,
                'inspection_criterion_id' => $criterion->id,
                'document_id' => $documentId,
                'note' => $note,
                'created_by' => $actor->id,
            ]);

            $this->audit->log(
                AuditAction::EvidenceLinked,
                $actor,
                $evidence,
                $inspection->application->institution_id,
                ['inspection_id' => $inspection->id, 'document_id' => $documentId],
            );

            return $evidence->refresh();
        });
    }

    /**
     * Unlink an evidence reference (the underlying document is untouched).
     *
     * @throws InspectionException
     */
    public function unlinkEvidence(User $actor, Inspection $inspection, InspectionEvidence $evidence): void
    {
        $this->assertCanConduct($actor, $inspection);

        if ($evidence->inspection_id !== $inspection->id) {
            throw InspectionException::notEditable();
        }

        $documentId = $evidence->document_id;
        $evidence->delete();

        $this->audit->log(
            AuditAction::EvidenceUnlinked,
            $actor,
            $inspection,
            $inspection->application->institution_id,
            ['inspection_id' => $inspection->id, 'document_id' => $documentId],
        );
    }

    /**
     * Checklist completion summary for the inspection.
     *
     * @return array<string, mixed>
     */
    public function summary(Inspection $inspection): array
    {
        $criteria = $inspection->criteria()->with(['evidence', 'reviewer'])->get();

        $counts = [
            'total' => $criteria->count(),
            'compliant' => 0,
            'partially_compliant' => 0,
            'noncompliant' => 0,
            'not_applicable' => 0,
            'not_observed' => 0,
            'not_started' => 0,
        ];

        foreach ($criteria as $criterion) {
            $key = match ($criterion->result) {
                InspectionResult::Compliant => 'compliant',
                InspectionResult::PartiallyCompliant => 'partially_compliant',
                InspectionResult::Noncompliant => 'noncompliant',
                InspectionResult::NotApplicable => 'not_applicable',
                InspectionResult::NotObserved => 'not_observed',
                default => 'not_started',
            };
            $counts[$key]++;
        }

        $counts['completed'] = $counts['compliant']
            + $counts['partially_compliant']
            + $counts['noncompliant']
            + $counts['not_applicable']
            + $counts['not_observed'];

        $counts['unresolved_notes'] = $criteria
            ->filter(fn (InspectionCriterion $c): bool => filled($c->inspector_notes))
            ->count();

        $counts['is_complete'] = $this->isComplete($inspection, $criteria);

        return $counts;
    }

    /**
     * Whether every applicable criterion has a result (completion gate).
     *
     * @param  Collection<int, InspectionCriterion>|null  $criteria
     */
    public function isComplete(Inspection $inspection, ?Collection $criteria = null): bool
    {
        $criteria ??= $inspection->criteria()->get();

        if ($criteria->isEmpty()) {
            return false;
        }

        return $criteria->every(fn (InspectionCriterion $c): bool => $c->result !== null && $c->result !== InspectionResult::NotStarted);
    }

    /**
     * Inspector conflict check: any assigned inspector already assigned to an
     * overlapping active inspection window.
     *
     * @param  array<int, int>  $inspectorIds
     * @param  \DateTimeInterface|string|null  $start
     * @param  \DateTimeInterface|string|null  $end
     * @return array<int, array<string, mixed>>
     */
    public function conflicts(array $inspectorIds, $start, $end): array
    {
        if ($start === null || $end === null) {
            return [];
        }

        $start = $start instanceof \DateTimeInterface ? $start : new \DateTimeImmutable($start);
        $end = $end instanceof \DateTimeInterface ? $end : new \DateTimeImmutable($end);

        $rows = InspectionInspector::query()
            ->whereIn('user_id', $inspectorIds)
            ->where('status', 'accepted')
            ->whereHas('inspection', function ($query) use ($start, $end): void {
                $query->whereNotIn('status', ['completed', 'cancelled'])
                    ->whereNotNull('scheduled_start_at')
                    ->whereNotNull('scheduled_end_at')
                    ->where('scheduled_start_at', '<', $end)
                    ->where('scheduled_end_at', '>', $start);
            })
            ->with('inspection')
            ->get();

        $conflicts = [];

        foreach ($rows as $row) {
            /** @var InspectionInspector $row */
            $conflicts[$row->user_id] = [
                'user_id' => $row->user_id,
                'inspection_id' => $row->inspection_id,
                'scheduled_start_at' => $row->inspection->scheduled_start_at?->toDateTimeString(),
                'scheduled_end_at' => $row->inspection->scheduled_end_at?->toDateTimeString(),
            ];
        }

        return $conflicts;
    }

    /**
     * Validate the scheduling fields and inspector selection.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, int>  $inspectorIds
     */
    private function validateSchedule(array $data, ?int $leadInspectorId, array $inspectorIds): void
    {
        if ($leadInspectorId === null && ($data['lead_inspector_id'] ?? null) === null) {
            throw InspectionException::leadInspectorRequired();
        }

        if ($leadInspectorId === null) {
            $leadInspectorId = $data['lead_inspector_id'];
        }

        $pool = array_values(array_unique(array_merge([$leadInspectorId], $inspectorIds), SORT_NUMERIC));

        if (($data['scheduled_start_at'] ?? null) === null || ($data['scheduled_end_at'] ?? null) === null) {
            throw InspectionException::dateOrder();
        }

        $start = new \DateTimeImmutable($data['scheduled_start_at']);
        $end = new \DateTimeImmutable($data['scheduled_end_at']);

        if ($start >= $end) {
            throw InspectionException::dateOrder();
        }

        if ($start < new \DateTimeImmutable('now')) {
            throw InspectionException::pastDate();
        }

        // Location or meeting URL required per inspection type.
        $type = $data['inspection_type'] ?? 'onsite';
        $hasLocation = filled($data['location'] ?? null);
        $hasMeeting = filled($data['meeting_url'] ?? null);

        if ($type === 'virtual' && ! $hasMeeting) {
            throw new InspectionException('A virtual inspection requires a meeting URL.', 'meeting_url');
        }

        if ($type === 'onsite' && ! $hasLocation) {
            throw new InspectionException('An on-site inspection requires a location.', 'location');
        }
    }

    /**
     * Enforce that a saved/updated result carries the required notes or
     * explanation.
     */
    private function assertResultRules(InspectionResult $result, ?string $notes): void
    {
        if ($result === InspectionResult::Noncompliant && blank($notes)) {
            throw InspectionException::notesRequired('non-compliant');
        }

        if ($result === InspectionResult::PartiallyCompliant && blank($notes)) {
            throw InspectionException::notesRequired('partially compliant');
        }

        if ($result === InspectionResult::NotObserved && blank($notes)) {
            throw InspectionException::explanationRequired('not observed');
        }

        if ($result === InspectionResult::NotApplicable && blank($notes)) {
            throw InspectionException::explanationRequired('not applicable');
        }
    }

    /**
     * Gate submission: every applicable criterion must have a result, and the
     * result-specific notes/explanation requirements must be satisfied.
     *
     * @throws InspectionException
     */
    private function assertComplete(Inspection $inspection): void
    {
        $criteria = $inspection->criteria()->get();

        foreach ($criteria as $criterion) {
            if ($criterion->result === null || $criterion->result === InspectionResult::NotStarted) {
                throw InspectionException::incompleteCriteria();
            }

            $this->assertResultRules($criterion->result, $criterion->inspector_notes);
        }
    }

    /**
     * An inspector may conduct an inspection only if they are an assigned
     * inspector or an authorized PSP/CART administrator.
     */
    private function assertCanConduct(User $actor, Inspection $inspection): void
    {
        if ($actor->canScheduleInspection()) {
            return;
        }

        if ($actor->isInspector() && $inspection->hasInspector($actor)) {
            return;
        }

        throw InspectionException::startAuthorization();
    }

    /**
     * Only authorized PSP/CART reviewers return or complete an inspection.
     */
    private function assertCanReview(User $reviewer): void
    {
        if (! $reviewer->canReviewInspection()) {
            throw InspectionException::reviewAuthorization();
        }
    }

    /**
     * Only authorized PSP/CART users schedule, assign, cancel, or reschedule.
     */
    private function assertCanSchedule(User $actor): void
    {
        if (! $actor->canScheduleInspection()) {
            throw InspectionException::scheduleAuthorization();
        }
    }

    /**
     * @param  callable(Inspection): array<string, mixed>  $columnUpdates
     * @param  array<string, mixed>  $extra
     */
    private function transition(
        User $actor,
        Inspection $inspection,
        InspectionStatus $to,
        callable $columnUpdates,
        AuditAction $action,
        string $comment = '',
        array $extra = [],
    ): Inspection {
        $from = $inspection->status;
        $institutionId = $inspection->application->institution_id;

        return DB::transaction(function () use ($actor, $inspection, $to, $columnUpdates, $action, $comment, $extra, $from, $institutionId): Inspection {
            $inspection->forceFill(array_merge(['status' => $to], $columnUpdates($inspection)))->save();

            $this->audit->log(
                $action,
                $actor,
                $inspection,
                $institutionId,
                array_merge([
                    'inspection_id' => $inspection->id,
                    'application_id' => $inspection->application_id,
                    'from_status' => $from?->value,
                    'to_status' => $to->value,
                    'comment' => $comment,
                ], $extra),
            );

            return $inspection->refresh();
        });
    }
}
