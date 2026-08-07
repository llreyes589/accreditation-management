<?php

namespace Database\Factories;

use App\Enums\InstitutionStatus;
use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Institution>
 */
class InstitutionFactory extends Factory
{
    protected $model = Institution::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company().' Medical Center',
            'code' => strtoupper(fake()->unique()->bothify('INST-####')),
            'status' => InstitutionStatus::Active,
            'contact_email' => fake()->unique()->companyEmail(),
            'contact_phone' => fake()->numerify('+63-##-###-####'),
            'address' => fake()->address(),
        ];
    }
}
