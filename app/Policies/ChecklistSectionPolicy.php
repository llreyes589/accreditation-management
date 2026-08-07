<?php

namespace App\Policies;

use App\Models\ChecklistSection;
use App\Models\User;

/**
 * The checklist is system-wide configuration: readable by any authenticated
 * user, writable only by PSP administrators.
 */
class ChecklistSectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ChecklistSection $section): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isPspAdministrator();
    }

    public function update(User $user, ChecklistSection $section): bool
    {
        return $user->isPspAdministrator();
    }

    public function delete(User $user, ChecklistSection $section): bool
    {
        return $user->isPspAdministrator();
    }
}
