<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain exception for the Findings & Corrective-Action workflow.
 *
 * Carries an optional form field so the controller can surface it as a
 * validated-input error. Mirrors the {@see InspectionException} contract.
 */
class FindingException extends RuntimeException
{
    public function __construct(string $message, private readonly string $field = 'finding')
    {
        parent::__construct($message);
    }

    public function field(): string
    {
        return $this->field;
    }

    public static function notCreatable(string $reason = 'This inspection is not in a state that permits findings.'): self
    {
        return new self($reason, 'inspection');
    }

    public static function criterionRequired(): self
    {
        return new self('A finding must reference an inspection criterion that has a recorded result.', 'inspection_criterion_id');
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self("Cannot move a finding from {$from} to {$to}.", 'status');
    }

    public static function notAcknowledged(): self
    {
        return new self('The institution must acknowledge the finding before this action.', 'status');
    }

    public static function notEditable(): self
    {
        return new self('This finding is locked and cannot be edited.', 'status');
    }

    public static function reopenReasonRequired(): self
    {
        return new self('A reason is required to reopen this finding.', 'comment');
    }

    public static function alreadyOpen(): self
    {
        return new self('This finding is already open.', 'status');
    }

    public static function notClosable(): self
    {
        return new self('A finding cannot be closed without an accepted corrective action or an authorized waiver.', 'status');
    }

    public static function waiverRequiresReason(): self
    {
        return new self('A waiver requires an authorized user and a reason.', 'void_reason');
    }

    public static function waiverAuthorization(): self
    {
        return new self('Only an authorized PSP/CART user may waive a finding.', 'status');
    }

    public static function voidRequiresReason(): self
    {
        return new self('Voiding a finding requires a reason.', 'void_reason');
    }

    public static function voidAuthorization(): self
    {
        return new self('Only an authorized administrator may void a finding.', 'status');
    }

    public static function cannotVoidClosed(): self
    {
        return new self('A closed finding cannot be voided.', 'status');
    }

    public static function createAuthorization(): self
    {
        return new self('Only an authorized PSP/CART reviewer may create findings.', 'status');
    }

    public static function reviewAuthorization(): self
    {
        return new self('Only an authorized PSP/CART reviewer may verify corrective actions.', 'status');
    }

    public static function institutionAuthorization(): self
    {
        return new self('Only a member of the owning institution may perform this action.', 'status');
    }

    public static function commentRequired(): self
    {
        return new self('A comment is required.', 'comment');
    }

    public static function assignedUserRequired(): self
    {
        return new self('A responsible user must be assigned.', 'responsible_user_id');
    }

    public static function dueDateRequired(): self
    {
        return new self('A due date is required.', 'due_date');
    }

    public static function rejectionReasonRequired(): self
    {
        return new self('A rejection reason is required.', 'rejection_reason');
    }

    public static function responseRequired(): self
    {
        return new self('A proposed action and evidence description are required.', 'proposed_action');
    }

    public static function notSubmittable(): self
    {
        return new self('This corrective action is not in a state that can be submitted.', 'status');
    }

    public static function closingSummaryRequired(): self
    {
        return new self('A closing summary is required to close a finding.', 'summary');
    }

    public static function evidenceAuthorization(): self
    {
        return new self('You may only link evidence documents from your own institution application.', 'evidence');
    }

    public static function notReviewable(): self
    {
        return new self('This corrective action is not in a reviewable state.', 'status');
    }
}
