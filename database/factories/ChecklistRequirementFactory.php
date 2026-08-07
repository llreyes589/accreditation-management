<?php

namespace Database\Factories;

use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistRequirement>
 */
class ChecklistRequirementFactory extends Factory
{
    protected $model = ChecklistRequirement::class;

    /**
     * Placeholder requirement text only. No official PSP accreditation
     * criteria are represented here.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'checklist_section_id' => ChecklistSection::factory(),
            'code' => strtoupper(fake()->unique()->bothify('REQ-####')),
            'requirement' => fake()->sentence(10),
            'guidance' => null,
            'weight' => 1,
            'pass_threshold' => null,
            'is_mandatory' => false,
            'applicable_program_types' => null,
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }
}
