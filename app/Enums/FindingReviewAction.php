<?php

namespace App\Enums;

/**
 * Historical review actions recorded in finding_reviews (append-only audit).
 */
enum FindingReviewAction: string
{
    case Created = 'created';
    case Opened = 'opened';
    case Acknowledged = 'acknowledged';
    case Submitted = 'submitted';
    case Returned = 'returned';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Reopened = 'reopened';
    case Closed = 'closed';
    case Waived = 'waived';
    case Voided = 'voided';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Created',
            self::Opened => 'Opened',
            self::Acknowledged => 'Acknowledged',
            self::Submitted => 'Submitted',
            self::Returned => 'Returned',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::Reopened => 'Reopened',
            self::Closed => 'Closed',
            self::Waived => 'Waived',
            self::Voided => 'Voided',
        };
    }
}
