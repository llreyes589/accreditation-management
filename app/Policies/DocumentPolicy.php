<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\Document;
use App\Models\User;

/**
 * Access control for accreditation supporting documents.
 *
 * Institution members may upload, replace, and view documents for their own
 * institution's applications; they can never change a review status. PSP
 * administrators, CART reviewers, inspectors, and read-only auditors may view
 * documents according to their cross-institution remit. Only authorized PSP /
 * CART reviewers may change review status. Every download and review action
 * re-checks this policy so a guessed URL id cannot leak another institution's
 * document.
 */
class DocumentPolicy
{
    /**
     * Roles that may take review actions (accept / reject / replace request).
     *
     * @var array<int, RoleName>
     */
    private const REVIEWER_ROLES = [
        RoleName::PspAdministrator,
        RoleName::CartReviewer,
    ];

    /**
     * Roles that may view documents irrespective of institution.
     *
     * @var array<int, RoleName>
     */
    private const VIEWER_ROLES = [
        RoleName::PspAdministrator,
        RoleName::CartReviewer,
        RoleName::Inspector,
        RoleName::ReadOnlyAuditor,
    ];

    /**
     * Whether the user belongs to the document's institution (or holds a
     * cross-institution institution membership).
     */
    private function ownsDocument(User $user, Document $document): bool
    {
        return $user->belongsToInstitution($document->application->institution_id);
    }

    private function isReviewer(User $user): bool
    {
        return $user->hasAnyRole(self::REVIEWER_ROLES);
    }

    private function isCrossInstitutionViewer(User $user): bool
    {
        return $user->hasAnyRole(self::VIEWER_ROLES);
    }

    /**
     * Upload a document to an application.
     */
    public function upload(User $user, AccreditationApplication $application): bool
    {
        return $user->belongsToInstitution($application->institution_id)
            && $user->hasAnyRole([
                RoleName::InstitutionAdministrator,
                RoleName::TrainingOfficer,
            ]);
    }

    /**
     * View document metadata. Cross-institution viewers (PSP/CART/inspector/
     * auditor) may always view; institution members only their own.
     */
    public function view(User $user, Document $document): bool
    {
        if ($this->isCrossInstitutionViewer($user)) {
            return true;
        }

        return $this->ownsDocument($user, $document);
    }

    /**
     * Download or preview the physical file. Identical to view, but explicitly
     * recorded so the rule stays separate from metadata viewing if it ever
     * diverges.
     */
    public function download(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    public function preview(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }

    /**
     * Upload a replacement. Only the owning institution's applicant roles, and
     * only for a replaceable document.
     */
    public function replace(User $user, Document $document): bool
    {
        if (! $document->acceptsReplacement()) {
            return false;
        }

        return $this->ownsDocument($user, $document)
            && $user->hasAnyRole([
                RoleName::InstitutionAdministrator,
                RoleName::TrainingOfficer,
            ]);
    }

    /**
     * Start reviewing a document.
     */
    public function startReview(User $user, Document $document): bool
    {
        return $this->isReviewer($user) && $document->isReviewable();
    }

    /**
     * Generic review permission (any decision). Specific decision validity is
     * enforced by the service layer.
     */
    public function review(User $user, Document $document): bool
    {
        return $this->isReviewer($user) && $document->isReviewable();
    }

    public function accept(User $user, Document $document): bool
    {
        return $this->isReviewer($user) && $document->isReviewable();
    }

    public function reject(User $user, Document $document): bool
    {
        return $this->isReviewer($user) && $document->isReviewable();
    }

    public function requestReplacement(User $user, Document $document): bool
    {
        return $this->isReviewer($user) && $document->isReviewable();
    }

    public function returnToReview(User $user, Document $document): bool
    {
        return $this->isReviewer($user) && $document->isReviewable();
    }

    /**
     * Withdraw a document. Only the owning institution's applicant roles, and
     * never when already accepted (accepted versions are immutable).
     */
    public function withdraw(User $user, Document $document): bool
    {
        if ($document->isAccepted() || $document->isWithdrawn()) {
            return false;
        }

        return $this->ownsDocument($user, $document)
            && $user->hasAnyRole([
                RoleName::InstitutionAdministrator,
                RoleName::TrainingOfficer,
            ]);
    }

    /**
     * View the document's version / review history.
     */
    public function viewHistory(User $user, Document $document): bool
    {
        return $this->view($user, $document);
    }
}
