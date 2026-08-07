<?php

namespace App\Policies;

use App\Models\ChecklistRequirement;
use App\Models\User;

/**
 * Administrator-only configuration: checklist requirements define the
 * compliance rules and may only be changed by PSP administrators.
 */
class ChecklistRequirementPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChecklistRequirement $requirement): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isPspAdministrator();
    }

    public function update(User $user, ChecklistRequirement $requirement): bool
    {
        return $user->isPspAdministrator();
    }

    public function delete(User $user, ChecklistRequirement $requirement): bool
    {
        return $user->isPspAdministrator();
    }
}
