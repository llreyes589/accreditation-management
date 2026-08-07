<?php

namespace Tests\Concerns;

use App\Enums\RoleName;
use App\Models\Institution;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;

trait CreatesFoundationData
{
    protected function seedRoles(): void
    {
        $this->seed(RoleSeeder::class);
    }

    /**
     * Create a user holding the given role.
     *
     * When $institution is provided the user becomes a member of it and the
     * role is scoped to that institution; otherwise the role is global.
     */
    protected function userWithRole(RoleName $role, ?Institution $institution = null): User
    {
        $this->seedRoles();

        $user = User::factory()->create();
        $roleModel = Role::where('name', $role->value)->firstOrFail();

        if ($institution !== null) {
            $user->institutions()->attach($institution->id, ['is_primary' => true]);
        }

        $user->roles()->attach($roleModel->id, [
            'institution_id' => $institution?->id,
        ]);

        return $user->fresh(['roles', 'institutions']);
    }
}
