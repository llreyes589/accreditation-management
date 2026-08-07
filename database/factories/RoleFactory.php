<?php

namespace Database\Factories;

use App\Enums\RoleName;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $role = fake()->randomElement(RoleName::cases());

        return [
            'name' => $role->value,
            'label' => $role->label(),
            'description' => null,
            'is_cross_institution' => $role->isCrossInstitution(),
        ];
    }

    public function named(RoleName $role): self
    {
        return $this->state(fn (): array => [
            'name' => $role->value,
            'label' => $role->label(),
            'is_cross_institution' => $role->isCrossInstitution(),
        ]);
    }
}
