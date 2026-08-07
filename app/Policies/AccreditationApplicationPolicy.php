<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\User;

/**
 * Access to accreditation applications.
 *
 * Institution members see only their own institution's applications. PSP
 * administrators, CART reviewers, inspectors and read-only auditors may view
 * applications across institutions; only institution staff may edit a draft.
 */
class AccreditationApplicationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCrossInstitutionRole() || $user->institutions->isNotEmpty();
    }

    public function view(User $user, AccreditationApplication $application): bool
    {
        return $user->hasCrossInstitutionRole()
            || $user->belongsToInstitution($application->institution_id);
    }

    public function create(User $user): bool
    {
        return $this->isInstitutionEditor($user);
    }

    /**
     * Institution staff may edit only while the application is editable, and
     * only within their own institution.
     */
    public function update(User $user, AccreditationApplication $application): bool
    {
        if (! $user->belongsToInstitution($application->institution_id)) {
            return false;
        }

        if (! $application->status->isEditableByInstitution()) {
            return false;
        }

        return $this->isInstitutionEditor($user, $application->institution_id);
    }

    public function submit(User $user, AccreditationApplication $application): bool
    {
        return $this->update($user, $application);
    }

    /**
     * Accreditation history is never deleted.
     */
    public function delete(User $user, AccreditationApplication $application): bool
    {
        return false;
    }

    private function isInstitutionEditor(User $user, ?int $institutionId = null): bool
    {
        return $user->hasAnyRole([
            RoleName::InstitutionAdministrator,
            RoleName::TrainingOfficer,
        ], $institutionId);
    }
}
