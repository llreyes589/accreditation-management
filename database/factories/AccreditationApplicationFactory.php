<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Models\AccreditationApplication;
use App\Models\Institution;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccreditationApplication>
 */
class AccreditationApplicationFactory extends Factory
{
    protected $model = AccreditationApplication::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'program_id' => Program::factory(),
            'reference' => strtoupper(fake()->unique()->bothify('APP-####-????')),
            'cycle_label' => null,
            'status' => ApplicationStatus::Draft,
            'submitted_by' => null,
            'submitted_at' => null,
            'notes' => null,
        ];
    }

    /**
     * Attach the application to an existing program, keeping institution_id
     * consistent with the program's owner.
     */
    public function forProgram(Program $program): self
    {
        return $this->state(fn (): array => [
            'program_id' => $program->id,
            'institution_id' => $program->institution_id,
        ]);
    }

    public function status(ApplicationStatus $status): self
    {
        return $this->state(fn (): array => ['status' => $status]);
    }
}
