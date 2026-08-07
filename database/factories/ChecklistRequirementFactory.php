<?php

namespace Database\Factories;

use App\Enums\RequirementSeverity;
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
            'severity' => RequirementSeverity::Minor,
            'weight' => 1,
            'pass_threshold' => null,
            'is_mandatory' => false,
            'is_reviewer_only' => false,
            'applicable_program_types' => null,
            'position' => fake()->numberBetween(0, 20),
            'is_active' => true,
        ];
    }

    public function mandatory(): self
    {
        return $this->state(fn (): array => [
            'is_mandatory' => true,
            'severity' => RequirementSeverity::Major,
        ]);
    }

    public function reviewerOnly(): self
    {
        return $this->state(fn (): array => ['is_reviewer_only' => true]);
    }

    public function severity(RequirementSeverity $severity): self
    {
        return $this->state(fn (): array => ['severity' => $severity]);
    }

    public function inactive(): self
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /**
     * @param  array<int, string>  $types
     */
    public function forProgramTypes(array $types): self
    {
        return $this->state(fn (): array => ['applicable_program_types' => $types]);
    }
}
