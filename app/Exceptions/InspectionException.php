<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Raised when an inspection transition is illegal or unmet by a precondition
 * (wrong state, ineligible application, conflict, missing data).
 *
 * Carries a validation-friendly message so controllers can surface it next to
 * the relevant field rather than returning a bare 500.
 */
class InspectionException extends RuntimeException
{
    public function __construct(string $message, private readonly string $field = 'inspection')
    {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }

    public static function notEligible(string $reason): self
    {
        return new self($reason, 'application');
    }

    public static function duplicateActive(): self
    {
        return new self('This application already has an active inspection.', 'application');
    }

    public static function notSchedulable(): self
    {
        return new self('Only a draft or rescheduled inspection can be scheduled.', 'status');
    }

    public static function notStartable(): self
    {
        return new self('Only a scheduled inspection can be started.', 'status');
    }

    public static function notSubmittable(): self
    {
        return new self('Only an in-progress inspection can be submitted for review.', 'status');
    }

    public static function notReturnable(): self
    {
        return new self('Only a submitted inspection can be returned for revision.', 'status');
    }

    public static function notCompletable(): self
    {
        return new self('Only a submitted inspection can be completed.', 'status');
    }

    public static function notCancellable(): self
    {
        return new self('This inspection cannot be cancelled in its current state.', 'status');
    }

    public static function notEditable(): self
    {
        return new self('The inspection checklist is locked in this status.', 'status');
    }

    public static function leadInspectorRequired(): self
    {
        return new self('A lead inspector is required.', 'lead_inspector_id');
    }

    public static function inspectorRequired(): self
    {
        return new self('At least one authorized inspector is required.', 'inspectors');
    }

    public static function inspectorConflict(): self
    {
        return new self('An assigned inspector has a conflicting inspection in the same period.', 'inspectors');
    }

    public static function sameInspectorTwice(): self
    {
        return new self('The same inspector cannot be assigned twice.', 'inspectors');
    }

    public static function dateOrder(): self
    {
        return new self('The start time must be before the end time.', 'scheduled_start_at');
    }

    public static function pastDate(): self
    {
        return new self('The scheduled date must not be in the past.', 'scheduled_start_at');
    }

    public static function scheduleAuthorization(): self
    {
        return new self('Scheduling an inspection requires an authorized PSP/CART user.', 'authorization');
    }

    public static function reviewAuthorization(): self
    {
        return new self('Completing or returning an inspection requires an authorized PSP/CART reviewer.', 'authorization');
    }

    public static function startAuthorization(): self
    {
        return new self('Only an assigned inspector or authorized administrator may start the inspection.', 'authorization');
    }

    public static function commentRequired(): self
    {
        return new self('A reviewer comment is required when returning an inspection for revision.', 'comment');
    }

    public static function summaryRequired(): self
    {
        return new self('A reviewer summary is required to complete the inspection.', 'summary');
    }

    public static function cancellationReasonRequired(): self
    {
        return new self('A cancellation reason is required.', 'cancellation_reason');
    }

    public static function incompleteCriteria(): self
    {
        return new self('Every applicable criterion must have a result before submission.', 'criteria');
    }

    public static function explanationRequired(string $result): self
    {
        return new self(
            'An explanation is required for criteria marked '.$result.'.',
            'criteria',
        );
    }

    public static function notesRequired(string $result): self
    {
        return new self(
            'Inspector notes are required for criteria marked '.$result.'.',
            'criteria',
        );
    }

    public static function reviewerRequired(): self
    {
        return new self('This action requires an authorized inspector or reviewer.', 'authorization');
    }
}
