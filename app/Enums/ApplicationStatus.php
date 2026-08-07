<?php

namespace App\Enums;

/**
 * Accreditation application workflow states (docs/workflows.md §2).
 *
 * The transition matrix (who may move an application between states) is
 * configurable authorization data and is NOT encoded here.
 */
enum ApplicationStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case InspectionScheduled = 'inspection_scheduled';
    case InspectionComplete = 'inspection_complete';
    case DecisionPending = 'decision_pending';
    case Decided = 'decided';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::InspectionScheduled => 'Inspection Scheduled',
            self::InspectionComplete => 'Inspection Complete',
            self::DecisionPending => 'Decision Pending',
            self::Decided => 'Decided',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * States in which the institution may still edit its own submission.
     */
    public function isEditableByInstitution(): bool
    {
        return $this === self::Draft;
    }

    /**
     * States representing an application still moving through the workflow.
     */
    public function isOpen(): bool
    {
        return ! in_array($this, [self::Decided, self::Withdrawn], true);
    }
}
