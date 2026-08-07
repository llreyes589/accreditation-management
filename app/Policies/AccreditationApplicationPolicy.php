<?php

namespace App\Policies;

use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\User;

/**
 * Access to accreditation applications.
 *
 * Institution members see only their own institution's applications. PSP
 * administrators, CART reviewers, inspectors and read-only auditors may view
 * applications across institutions but can never act as the applicant.
 */
class AccreditationApplicationPolicy
{
    /**
     * Roles permitted to author an institution's application.
     *
     * Residents and consultants are deliberately excluded.
     */
    private const APPLICANT_ROLES = [
        RoleName::InstitutionAdministrator,
        RoleName::TrainingOfficer,
    ];

    public function viewAny(User $user): bool
    {
        return $user->hasCrossInstitutionRole() || $user->institutions->isNotEmpty();
    }

    public function view(User $user, AccreditationApplication $application): bool
    {
        if ($user->hasCrossInstitutionRole()) {
            // Reviewers see submitted work, not private in-progress drafts.
            return $application->status !== ApplicationStatus::Draft
                || $user->isPspAdministrator();
        }

        return $user->belongsToInstitution($application->institution_id);
    }

    public function create(User $user): bool
    {
        return $this->isApplicant($user);
    }

    /**
     * Institution staff may edit only while the application is editable, and
     * only within their own institution.
     *
     * Cross-institution reviewers are excluded outright: viewing an
     * application never confers the right to act as the applicant.
     */
    public function update(User $user, AccreditationApplication $application): bool
    {
        if (! $user->belongsToInstitution($application->institution_id)) {
            return false;
        }

        if (! $application->isEditableByApplicant()) {
            return false;
        }

        return $this->isApplicant($user, $application->institution_id);
    }

    public function submit(User $user, AccreditationApplication $application): bool
    {
        return $this->update($user, $application);
    }

    /**
     * Only an unsubmitted draft may be withdrawn.
     */
    public function withdraw(User $user, AccreditationApplication $application): bool
    {
        return $application->status === ApplicationStatus::Draft
            && $user->belongsToInstitution($application->institution_id)
            && $this->isApplicant($user, $application->institution_id);
    }

    /**
     * Accreditation history is never deleted.
     */
    public function delete(User $user, AccreditationApplication $application): bool
    {
        return false;
    }

    private function isApplicant(User $user, ?int $institutionId = null): bool
    {
        return $user->hasAnyRole(self::APPLICANT_ROLES, $institutionId);
    }
}
