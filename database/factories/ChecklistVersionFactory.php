<?php

namespace Database\Factories;

use App\Enums\ChecklistVersionStatus;
use App\Enums\ProgramType;
use App\Models\ChecklistVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistVersion>
 */
class ChecklistVersionFactory extends Factory
{
    protected $model = ChecklistVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'version_code' => 'v'.fake()->unique()->numerify('####'),
            'name' => implode(' ', (array) fake()->words(3, false)).' Checklist',
            'description' => null,
            'program_type' => ProgramType::CombinedPathology,
            'status' => ChecklistVersionStatus::Draft,
            'effective_from' => null,
            'effective_until' => null,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'status' => ChecklistVersionStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function retired(): self
    {
        return $this->state(fn (): array => [
            'status' => ChecklistVersionStatus::Retired,
            'retired_at' => now(),
        ]);
    }
}
