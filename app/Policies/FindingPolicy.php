<?php

namespace App\Policies;

use App\Models\Finding;
use App\Models\User;

/**
 * Access control for findings and corrective actions.
 *
 * PSP administrators and CART reviewers (cross-institution) may create, classify,
 * require corrective actions, and verify. Institution members (Inst Admin /
 * Training Officer) acknowledge and submit/withdraw corrective actions for
 * their own institution. Auditors may view only. Every action re-checks this
 * policy so a guessed URL cannot expose another institution's finding.
 */
class FindingPolicy
{
    /**
     * Anyone with an inspection-facing or institution role may see the list.
     */
    public function viewAny(User $user): bool
    {
        return $user->canReviewInspection()
            || $user->isAuditor()
            || $user->institutions->isNotEmpty();
    }

    /**
     * View a single finding. PSP/CART and auditors see any; institution members
     * see only their own institution's.
     */
    public function view(User $user, Finding $finding): bool
    {
        if ($user->canReviewInspection() || $user->isAuditor()) {
            return true;
        }

        return $user->belongsToInstitution($finding->institutionId());
    }

    /**
     * Create a finding (PSP/CART only).
     */
    public function create(User $user): bool
    {
        return $user->canReviewInspection();
    }

    /**
     * Open / waive / void / require-corrective-action — PSP/CART reviewers.
     */
    public function manage(User $user, Finding $finding): bool
    {
        return $user->canReviewInspection();
    }

    /**
     * Institution acknowledges a finding (own institution only).
     */
    public function acknowledge(User $user, Finding $finding): bool
    {
        return $user->belongsToInstitution($finding->institutionId());
    }

    /**
     * Submit / withdraw a corrective action or response (institution member of
     * the owning institution).
     */
    public function respond(User $user, Finding $finding): bool
    {
        return $user->belongsToInstitution($finding->institutionId());
    }

    /**
     * Reviewer verify (accept/reject/reopen/resolve/close) a finding or action.
     */
    public function review(User $user, Finding $finding): bool
    {
        return $user->canReviewInspection();
    }
}
