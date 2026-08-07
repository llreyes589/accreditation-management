<?php

namespace App\Enums;

/**
 * Accreditation application workflow states.
 *
 * The full vocabulary is declared here so that later workflow milestones have
 * a single source of truth. This milestone only actively transitions between
 * Draft, Submitted and Withdrawn; the remaining cases are reserved.
 *
 * The transition matrix (who may move an application between states) is
 * configurable authorization data and is NOT encoded here.
 */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case ReturnedForRevision = 'returned_for_revision';
    case CompleteForInspection = 'complete_for_inspection';
    case InspectionScheduled = 'inspection_scheduled';
    case InspectionCompleted = 'inspection_completed';
    case FindingsIssued = 'findings_issued';
    case CorrectiveActionRequired = 'corrective_action_required';
    case ForFinalEvaluation = 'for_final_evaluation';
    case Approved = 'approved';
    case Probationary = 'probationary';
    case NotApproved = 'not_approved';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';
    case NotStarted = 'not_started';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::ReturnedForRevision => 'Returned for Revision',
            self::CompleteForInspection => 'Complete for Inspection',
            self::InspectionScheduled => 'Inspection Scheduled',
            self::InspectionCompleted => 'Inspection Completed',
            self::FindingsIssued => 'Findings Issued',
            self::CorrectiveActionRequired => 'Corrective Action Required',
            self::ForFinalEvaluation => 'For Final Evaluation',
            self::Approved => 'Approved',
            self::Probationary => 'Probationary',
            self::NotApproved => 'Not Approved',
            self::Withdrawn => 'Withdrawn',
            self::Expired => 'Expired',
            self::NotStarted => 'Not Started',
        };
    }

    /**
     * Statuses the applicant may still edit through the wizard.
     *
     * ReturnedForRevision is included so that a later milestone can reopen an
     * application for the institution without changing this contract.
     */
    public function isEditableByInstitution(): bool
    {
        return in_array($this, [self::Draft, self::ReturnedForRevision], true);
    }

    /**
     * Whether the application still occupies an accreditation cycle for its
     * program. Used to reject duplicate active applications.
     */
    public function isActive(): bool
    {
        return ! in_array($this, [
            self::Withdrawn,
            self::NotApproved,
            self::Expired,
        ], true);
    }

    /**
     * Whether the application is still moving through the workflow.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [
            self::Approved,
            self::NotApproved,
            self::Withdrawn,
            self::Expired,
        ], true);
    }

    /**
     * Statuses that represent a terminal outcome.
     */
    public function isTerminal(): bool
    {
        return ! $this->isOpen();
    }

    /**
     * @return array<int, string>
     */
    public static function activeValues(): array
    {
        return array_values(array_map(
            fn (self $status): string => $status->value,
            array_filter(self::cases(), fn (self $status): bool => $status->isActive()),
        ));
    }
}
