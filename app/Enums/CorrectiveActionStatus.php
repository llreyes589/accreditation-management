<?php

namespace App\Enums;

use App\Services\Accreditation\CorrectiveActionService;

/**
 * Lifecycle states for a corrective action attached to a finding.
 *
 * Enforced server-side in {@see CorrectiveActionService}.
 *
 * DRAFT  -> OPEN              (assigned)
 * OPEN   -> IN_PROGRESS      (institution begins work)
 * OPEN   -> SUBMITTED        (institution submits response/evidence)
 * IN_PROGRESS -> SUBMITTED
 * SUBMITTED -> UNDER_REVIEW  (reviewer picks up)
 * UNDER_REVIEW -> ACCEPTED   (verified)
 * UNDER_REVIEW -> REJECTED   (returned for correction, reason required)
 * REJECTED -> IN_PROGRESS    (institution reworks)
 * REJECTED -> SUBMITTED
 * OPEN/IN_PROGRESS -> WITHDRAWN
 * ACCEPTED -> CLOSED
 *
 * OVERDUE is a derived flag (due date passed, not resolved), recomputed from
 * due_date; it is never a terminal state.
 */
enum CorrectiveActionStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Overdue = 'overdue';
    case Closed = 'closed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Open',
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Overdue => 'Overdue',
            self::Closed => 'Closed',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Open],
            self::Open => [self::InProgress, self::Submitted, self::Withdrawn],
            self::InProgress => [self::Submitted, self::Withdrawn],
            self::Submitted => [self::UnderReview, self::Accepted, self::Rejected],
            self::UnderReview => [self::Accepted, self::Rejected],
            self::Rejected => [self::InProgress, self::Submitted],
            self::Accepted => [self::Closed],
            self::Overdue => [self::InProgress, self::Submitted, self::UnderReview, self::Accepted, self::Rejected, self::Closed, self::Withdrawn],
            self::Closed => [],
            self::Withdrawn => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->transitions(), true);
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Withdrawn], true);
    }
}
