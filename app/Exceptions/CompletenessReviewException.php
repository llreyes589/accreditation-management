<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when a completeness-review transition is illegal or unmet by a
 * precondition (wrong state, missing reviewer comment, blocking items remain).
 *
 * Carries a validation-friendly message so controllers can surface it next to
 * the relevant field rather than returning a bare 500.
 */
class CompletenessReviewException extends RuntimeException
{
    public function __construct(string $message, private readonly string $field = 'application')
    {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }

    public static function notSubmittable(): self
    {
        return new self('Only a submitted application can begin completeness review.', 'status');
    }

    public static function notUnderReview(): self
    {
        return new self('Only an application under completeness review can be acted on.', 'status');
    }

    public static function notReturned(): self
    {
        return new self('Only a returned application can be resubmitted.', 'status');
    }

    public static function commentRequired(): self
    {
        return new self('A reviewer comment is required when returning an application for revision.', 'comment');
    }

    public static function blockingItems(int $count): self
    {
        return new self(
            $count.' blocking item(s) remain. Resolve all deficiencies before marking the application complete for inspection.',
            'completeness',
        );
    }

    public static function reviewerRequired(): self
    {
        return new self('A completeness review action requires an authorized reviewer.', 'authorization');
    }
}
