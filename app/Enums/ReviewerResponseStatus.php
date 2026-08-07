<?php

namespace App\Enums;

/**
 * PSP/CART reviewer verdict on a checklist requirement.
 *
 * Reviewer-only. Applicants can never write this value; the wizard neither
 * exposes nor accepts it.
 */
enum ReviewerResponseStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case NeedsClarification = 'needs_clarification';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending Review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::NeedsClarification => 'Needs Clarification',
        };
    }
}
