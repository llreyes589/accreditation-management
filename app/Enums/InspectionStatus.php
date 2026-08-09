<?php

namespace App\Enums;

use App\Services\Accreditation\InspectionService;

/**
 * Inspection workflow states for the inspection-scheduling + checklist milestone.
 *
 * The transition matrix is enforced server-side in {@see InspectionService}
 * (and the policy layer); the client never drives a transition.
 *
 * Allowed transitions:
 *   DRAFT                   -> SCHEDULED
 *   SCHEDULED               -> IN_PROGRESS
 *   SCHEDULED               -> RESCHEDULED
 *   SCHEDULED               -> CANCELLED
 *   RESCHEDULED             -> SCHEDULED
 *   IN_PROGRESS             -> SUBMITTED_FOR_REVIEW
 *   SUBMITTED_FOR_REVIEW    -> IN_PROGRESS          (returned by reviewer)
 *   SUBMITTED_FOR_REVIEW    -> COMPLETED
 *   IN_PROGRESS             -> CANCELLED            (authorized)
 *   COMPLETED               -> (terminal)
 */
enum InspectionStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case SubmittedForReview = 'submitted_for_review';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::InProgress => 'In Progress',
            self::SubmittedForReview => 'Submitted for Review',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
            self::Rescheduled => 'Rescheduled',
        };
    }

    /**
     * Statuses from which an inspection may move into SCHEDULED.
     */
    public function isSchedulable(): bool
    {
        return in_array($this, [self::Draft, self::Rescheduled], true);
    }

    /**
     * The inspection is actively booked and may be started or cancelled.
     */
    public function isScheduled(): bool
    {
        return in_array($this, [self::Scheduled, self::Rescheduled], true);
    }

    /**
     * A checklist may be worked on only while IN_PROGRESS. Once submitted for
     * review it is locked against ordinary editing (a returned inspection moves
     * back to IN_PROGRESS).
     */
    public function isEditable(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Terminal states never change again.
     */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled], true);
    }

    /**
     * The inspection is recorded and locked against ordinary editing.
     */
    public function isLocked(): bool
    {
        return in_array($this, [self::SubmittedForReview, self::Completed, self::Cancelled], true);
    }
}
