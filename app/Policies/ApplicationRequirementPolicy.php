<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ApplicationRequirement;
use App\Models\User;

/**
 * An institution's response to a checklist requirement.
 *
 * Visibility follows the parent application's institution isolation. Editing
 * the response belongs to institution staff; recording a review verdict
 * belongs to CART reviewers and PSP administrators.
 */
class ApplicationRequirementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCrossInstitutionRole() || $user->institutions->isNotEmpty();
    }

    public function view(User $user, ApplicationRequirement $requirement): bool
    {
        return $user->hasCrossInstitutionRole()
            || $user->belongsToInstitution($requirement->application->institution_id);
    }

    public function update(User $user, ApplicationRequirement $requirement): bool
    {
        $application = $requirement->application;

        if (! $user->belongsToInstitution($application->institution_id)) {
            return false;
        }

        if (! $application->status->isEditableByInstitution()) {
            return false;
        }

        return $user->hasAnyRole([
            RoleName::InstitutionAdministrator,
            RoleName::TrainingOfficer,
        ], $application->institution_id);
    }

    public function review(User $user, ApplicationRequirement $requirement): bool
    {
        return $user->hasAnyRole([
            RoleName::PspAdministrator,
            RoleName::CartReviewer,
        ]);
    }

    public function delete(User $user, ApplicationRequirement $requirement): bool
    {
        return false;
    }
}
