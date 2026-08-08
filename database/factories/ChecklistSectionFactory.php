<?php

namespace Database\Factories;

use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistSection>
 */
class ChecklistSectionFactory extends Factory
{
    protected $model = ChecklistSection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_version_id' => ChecklistVersion::factory(),
            'code' => strtoupper(fake()->unique()->bothify('SEC-####')),
            'title' => fake()->words(3, true),
            'description' => null,
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
