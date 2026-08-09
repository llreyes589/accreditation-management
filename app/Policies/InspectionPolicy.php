<?php

namespace App\Policies;

use App\Enums\InspectionStatus;
use App\Models\Inspection;
use App\Models\User;

/**
 * Access control for inspections.
 *
 * PSP administrators and CART reviewers may create, schedule, assign, return and
 * complete inspections. Assigned inspectors may view and conduct (start, edit
 * criteria, submit) their own inspection. Institution members may view only
 * their own institution's inspection schedule and authorized review outputs.
 * Auditors may view history but cannot modify. Every action re-checks this
 * policy so a guessed URL cannot expose another institution's inspection.
 */
class InspectionPolicy
{
    /**
     * Anyone with an inspection-facing role may see the list of inspections.
     */
    public function viewAny(User $user): bool
    {
        return $user->canInspect()
            || $user->institutions->isNotEmpty();
    }

    /**
     * View a single inspection.
     *
     * PSP/CART reviewers and auditors may view any inspection (cross-institution
     * remit). Assigned inspectors may view only inspections they are assigned to.
     * Institution members (and unassigned inspectors) may view only inspections
     * belonging to their own institution. A guessed URL cannot expose another
     * institution's inspection to an unassigned inspector.
     */
    public function view(User $user, Inspection $inspection): bool
    {
        if ($user->canReviewInspection() || $user->isAuditor()) {
            return true;
        }

        if ($user->isInspector() && $inspection->hasInspector($user)) {
            return true;
        }

        return $user->belongsToInstitution($inspection->application->institution_id);
    }

    /**
     * Create an inspection for an application (PSP/CART only).
     */
    public function create(User $user): bool
    {
        return $user->canScheduleInspection();
    }

    /**
     * Schedule an inspection (PSP/CART only).
     */
    public function schedule(User $user, Inspection $inspection): bool
    {
        return $user->canScheduleInspection()
            && $inspection->status->isSchedulable();
    }

    /**
     * Assign or remove inspectors, or change the lead (PSP/CART only).
     */
    public function assignInspectors(User $user, Inspection $inspection): bool
    {
        return $user->canScheduleInspection()
            && ! $inspection->status->isTerminal()
            && $inspection->status !== InspectionStatus::InProgress;
    }

    /**
     * Start the inspection: an assigned inspector or authorized administrator.
     */
    public function start(User $user, Inspection $inspection): bool
    {
        if ($user->canScheduleInspection()) {
            return $inspection->status->isScheduled();
        }

        return $user->isInspector()
            && $inspection->hasInspector($user)
            && $inspection->status->isScheduled();
    }

    /**
     * Save/update checklist criteria: an assigned inspector or administrator
     * while the inspection is editable.
     */
    public function updateCriterion(User $user, Inspection $inspection): bool
    {
        if ($user->canScheduleInspection()) {
            return $inspection->status->isEditable();
        }

        return $user->isInspector()
            && $inspection->hasInspector($user)
            && $inspection->status->isEditable();
    }

    /**
     * Submit the inspection for review.
     */
    public function submit(User $user, Inspection $inspection): bool
    {
        if ($user->canScheduleInspection()) {
            return $inspection->status === InspectionStatus::InProgress;
        }

        return $user->isInspector()
            && $inspection->hasInspector($user)
            && $inspection->status === InspectionStatus::InProgress;
    }

    /**
     * Return a submitted inspection for revision (PSP/CART reviewer only).
     */
    public function return(User $user, Inspection $inspection): bool
    {
        return $user->canReviewInspection()
            && $inspection->status === InspectionStatus::SubmittedForReview;
    }

    /**
     * Complete a submitted inspection (PSP/CART reviewer only).
     */
    public function complete(User $user, Inspection $inspection): bool
    {
        return $user->canReviewInspection()
            && $inspection->status === InspectionStatus::SubmittedForReview;
    }

    /**
     * Cancel an inspection (PSP/CART only, before completion).
     */
    public function cancel(User $user, Inspection $inspection): bool
    {
        return $user->canScheduleInspection()
            && ! $inspection->status->isTerminal();
    }

    /**
     * Reschedule an inspection (PSP/CART only).
     */
    public function reschedule(User $user, Inspection $inspection): bool
    {
        return $user->canScheduleInspection()
            && $inspection->status->isScheduled();
    }

    /**
     * Link or unlink evidence: an assigned inspector or administrator while
     * the inspection is editable.
     */
    public function manageEvidence(User $user, Inspection $inspection): bool
    {
        if ($user->canScheduleInspection()) {
            return $inspection->status->isEditable();
        }

        return $user->isInspector()
            && $inspection->hasInspector($user)
            && $inspection->status->isEditable();
    }

    /**
     * Institution members view only their own inspection schedule; they may
     * never edit inspection criteria.
     */
    public function institutionView(User $user, Inspection $inspection): bool
    {
        return $user->belongsToInstitution($inspection->application->institution_id);
    }
}
