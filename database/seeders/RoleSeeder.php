<?php

namespace Database\Seeders;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Seeds the eight structural roles. Idempotent.
 */
class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (RoleName::cases() as $role) {
            Role::updateOrCreate(
                ['name' => $role->value],
                [
                    'label' => $role->label(),
                    'is_cross_institution' => $role->isCrossInstitution(),
                ],
            );
        }
    }
}
