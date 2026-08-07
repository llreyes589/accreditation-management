<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\Program;
use App\Models\User;

class ProgramPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasCrossInstitutionRole() || $user->institutions->isNotEmpty();
    }

    public function view(User $user, Program $program): bool
    {
        return $user->hasCrossInstitutionRole()
            || $user->belongsToInstitution($program->institution_id);
    }

    public function create(User $user): bool
    {
        return $user->isPspAdministrator();
    }

    public function update(User $user, Program $program): bool
    {
        if ($user->isPspAdministrator()) {
            return true;
        }

        return $user->belongsToInstitution($program->institution_id)
            && $user->hasRole(RoleName::InstitutionAdministrator, $program->institution_id);
    }

    public function delete(User $user, Program $program): bool
    {
        return false;
    }
}
