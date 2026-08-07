<?php

namespace App\Policies;

use App\Models\Institution;
use App\Models\User;

/**
 * Institution-level data isolation.
 *
 * A user may read an institution only if they are a member of it, or hold a
 * cross-institution role. Only PSP administrators may create or modify
 * institution records.
 */
class InstitutionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCrossInstitutionRole() || $user->institutions->isNotEmpty();
    }

    public function view(User $user, Institution $institution): bool
    {
        return $user->hasCrossInstitutionRole()
            || $user->belongsToInstitution($institution->id);
    }

    public function create(User $user): bool
    {
        return $user->isPspAdministrator();
    }

    public function update(User $user, Institution $institution): bool
    {
        return $user->isPspAdministrator();
    }

    /**
     * Historical accreditation records must not be destroyed, so institutions
     * are never deletable through the application.
     */
    public function delete(User $user, Institution $institution): bool
    {
        return false;
    }
}
