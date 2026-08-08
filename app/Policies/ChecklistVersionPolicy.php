<?php

namespace App\Policies;

use App\Enums\RoleName;
use App\Models\ChecklistVersion;
use App\Models\User;

/**
 * Access control for the configurable checklist administration module.
 *
 * Only PSP administrators and authorized CART reviewers may create, edit,
 * publish, retire, clone, or delete checklist versions. Institution staff,
 * consultants, residents, inspectors and auditors — even those with a
 * cross-institution remit — are never allowed to modify checklist
 * definitions. Authorization is enforced server-side here and re-checked in
 * every controller, so a guessed URL id cannot mutate a version.
 *
 * A DRAFT version may be edited or deleted only while no application yet
 * references it. PUBLISHED and RETIRED versions are read-only.
 */
class ChecklistVersionPolicy
{
    /**
     * Roles permitted to administer the configurable checklist.
     *
     * @var array<int, RoleName>
     */
    private const ADMIN_ROLES = [
        RoleName::PspAdministrator,
        RoleName::CartReviewer,
    ];

    /**
     * Any authenticated user may see the list/detail pages in read mode, but
     * only checklist administrators may take action.
     */
    public function viewAny(User $user): bool
    {
        return $this->isChecklistAdministrator($user);
    }

    public function view(User $user, ChecklistVersion $version): bool
    {
        return $this->isChecklistAdministrator($user);
    }

    /**
     * Create a new (DRAFT) checklist version.
     */
    public function create(User $user): bool
    {
        return $this->isChecklistAdministrator($user);
    }

    /**
     * Edit a DRAFT version. PUBLISHED/RETIRED versions are immutable.
     */
    public function update(User $user, ChecklistVersion $version): bool
    {
        return $this->isChecklistAdministrator($user) && $version->isEditable();
    }

    /**
     * Delete an unused DRAFT version (never one referenced by an application).
     */
    public function delete(User $user, ChecklistVersion $version): bool
    {
        return $this->isChecklistAdministrator($user) && $version->isEditable();
    }

    /**
     * Clone any version into a new DRAFT.
     */
    public function clone(User $user, ChecklistVersion $version): bool
    {
        return $this->isChecklistAdministrator($user);
    }

    /**
     * Publish a DRAFT version.
     */
    public function publish(User $user, ChecklistVersion $version): bool
    {
        return $this->isChecklistAdministrator($user) && $version->isEditable();
    }

    /**
     * Retire a PUBLISHED version.
     */
    public function retire(User $user, ChecklistVersion $version): bool
    {
        return $this->isChecklistAdministrator($user) && $version->isPublished();
    }

    /**
     * View the applicant-facing preview.
     */
    public function preview(User $user, ChecklistVersion $version): bool
    {
        return $this->isChecklistAdministrator($user);
    }

    /**
     * Manage sections within a DRAFT version.
     */
    public function manageSections(User $user, ChecklistVersion $version): bool
    {
        return $this->update($user, $version);
    }

    /**
     * Manage requirements within a DRAFT version.
     */
    public function manageRequirements(User $user, ChecklistVersion $version): bool
    {
        return $this->update($user, $version);
    }

    private function isChecklistAdministrator(User $user): bool
    {
        return $user->hasAnyRole(self::ADMIN_ROLES);
    }
}
