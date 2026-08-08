<?php

namespace App\Enums;

/**
 * Reviewer decision recorded on a document review.
 */
enum DocumentDecision: string
{
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ReplacementRequested = 'replacement_requested';
    case ReturnedToReview = 'returned_to_review';

    public function label(): string
    {
        return match ($this) {
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::ReplacementRequested => 'Replacement Requested',
            self::ReturnedToReview => 'Returned to Review',
        };
    }

    /**
     * Decisions that must carry a reviewer comment.
     */
    public function requiresComment(): bool
    {
        return in_array($this, [
            self::Rejected,
            self::ReplacementRequested,
            self::ReturnedToReview,
        ], true);
    }

    /**
     * Map a decision to the resulting document status.
     */
    public function resultingStatus(): DocumentStatus
    {
        return match ($this) {
            self::Accepted => DocumentStatus::Accepted,
            self::Rejected => DocumentStatus::Rejected,
            self::ReplacementRequested => DocumentStatus::ReplacementRequested,
            self::ReturnedToReview => DocumentStatus::UnderReview,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $case): string => $case->value, self::cases());
    }
}
