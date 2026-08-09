<?php

namespace Database\Factories;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;
use App\Models\ChecklistVersion;
use App\Models\Institution;
use App\Models\Program;
use App\Models\User;
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
            'application_type' => ApplicationType::New,
            'checklist_version' => config('accreditation.checklist_version'),
            'checklist_version_id' => ChecklistVersion::where('version_code', config('accreditation.checklist_version'))
                ->first()?->id,
            'application_reference' => strtoupper(fake()->unique()->bothify('APP-####-????')),
            'cycle_label' => null,
            'status' => ApplicationStatus::Draft,
            'current_step' => WizardStep::ProgramSelection,
            'completion_percentage' => 0,
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

    public function type(ApplicationType $type): self
    {
        return $this->state(fn (): array => ['application_type' => $type]);
    }

    /**
     * A draft with every free-form wizard section filled in.
     */
    public function withCompletedSections(): self
    {
        return $this->state(fn (): array => [
            'program_details' => [
                'program_head' => fake()->name(),
                'training_duration_years' => 4,
                'approved_resident_slots' => 6,
            ],
            'institution_information' => [
                'bed_capacity' => 250,
                'ownership_type' => 'private',
                'accrediting_bodies' => 'Synthetic test data',
            ],
            'leadership_staffing' => [
                'program_director' => fake()->name(),
                'consultant_count' => 8,
                'support_staff_count' => 12,
            ],
            'facilities_services' => [
                'laboratory_sections' => 'Histopathology, Clinical Chemistry',
                'annual_specimen_volume' => 15000,
                'equipment_summary' => 'Synthetic test data',
            ],
            'curriculum_training' => [
                'curriculum_summary' => 'Synthetic test data',
                'rotation_schedule' => 'Synthetic test data',
                'evaluation_methods' => 'Synthetic test data',
            ],
        ]);
    }

    /**
     * A draft that has accepted the declaration.
     */
    public function withDeclaration(?User $user = null): self
    {
        return $this->state(fn (): array => [
            'declaration_accepted_at' => now(),
            'declaration_accepted_by' => $user instanceof User ? $user->id : User::factory(),
        ]);
    }
}
