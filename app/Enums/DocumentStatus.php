<?php

namespace App\Enums;

/**
 * Lifecycle status of a logical document submission.
 *
 * A document starts UPLOADED, moves through reviewer states, and may be
 * superseded by a replacement or withdrawn. Accepted versions are immutable.
 */
enum DocumentStatus: string
{
    case Uploaded = 'uploaded';
    case UnderReview = 'under_review';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case ReplacementRequested = 'replacement_requested';
    case Superseded = 'superseded';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::UnderReview => 'Under Review',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
            self::ReplacementRequested => 'Replacement Requested',
            self::Superseded => 'Superseded',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Reviewer can act on the document in this state.
     */
    public function isReviewable(): bool
    {
        return in_array($this, [
            self::Uploaded,
            self::UnderReview,
            self::ReplacementRequested,
            self::Rejected,
        ], true);
    }

    /**
     * An accepted version satisfies a required-document rule.
     */
    public function isAccepted(): bool
    {
        return $this === self::Accepted;
    }

    /**
     * Document does not satisfy a required-document rule.
     */
    public function blocksCompleteness(): bool
    {
        return in_array($this, [
            self::Rejected,
            self::ReplacementRequested,
        ], true);
    }
}
