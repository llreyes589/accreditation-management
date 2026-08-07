<?php

namespace Database\Factories;

use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
use App\Models\Institution;
use App\Models\Program;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Program>
 */
class ProgramFactory extends Factory
{
    protected $model = Program::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(ProgramType::cases());

        return [
            'institution_id' => Institution::factory(),
            'name' => $type->label().' Residency Training Program',
            'program_type' => $type,
            'status' => ProgramStatus::Active,
            'accredited_from' => null,
            'accredited_until' => null,
        ];
    }

    /**
     * Mark the program as currently accredited.
     *
     * The caller supplies the validity window explicitly; the factory does not
     * assume any accreditation duration.
     */
    public function accredited(?string $from = null, ?string $until = null): self
    {
        return $this->state(fn (): array => [
            'status' => ProgramStatus::Accredited,
            'accredited_from' => $from,
            'accredited_until' => $until,
        ]);
    }
}
