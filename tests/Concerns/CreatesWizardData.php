<?php

namespace Tests\Concerns;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
use App\Enums\RequirementSeverity;
use App\Models\AccreditationApplication;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\Institution;
use App\Models\Program;
use App\Models\User;
use App\Services\Accreditation\ChecklistResponseSynchronizer;

/**
 * Helpers for building synthetic accreditation wizard fixtures.
 *
 * All data is fictional; no real institution or PSP criteria are used.
 */
trait CreatesWizardData
{
    protected function activeProgram(?Institution $institution = null): Program
    {
        return Program::factory()
            ->for($institution ?? Institution::factory())
            ->create([
                'program_type' => ProgramType::CombinedPathology,
                'status' => ProgramStatus::Active,
            ]);
    }

    /**
     * Create a small synthetic checklist.
     *
     * @return array{section: ChecklistSection, mandatory: ChecklistRequirement, optional: ChecklistRequirement, reviewerOnly: ChecklistRequirement}
     */
    protected function synthChecklist(): array
    {
        $section = ChecklistSection::factory()->create([
            'title' => 'Synthetic Section',
            'position' => 0,
        ]);

        return [
            'section' => $section,
            'mandatory' => ChecklistRequirement::factory()->for($section, 'section')->mandatory()->create([
                'requirement' => '[SYNTHETIC] Mandatory requirement for testing.',
                'position' => 0,
            ]),
            'optional' => ChecklistRequirement::factory()->for($section, 'section')->create([
                'requirement' => '[SYNTHETIC] Optional requirement for testing.',
                'severity' => RequirementSeverity::Minor,
                'position' => 1,
            ]),
            'reviewerOnly' => ChecklistRequirement::factory()->for($section, 'section')->reviewerOnly()->create([
                'requirement' => '[SYNTHETIC] Reviewer-only requirement.',
                'position' => 2,
            ]),
        ];
    }

    /**
     * The free-form payload for every data-carrying wizard step.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function completeSectionPayloads(): array
    {
        return [
            'application_details' => [
                'program_head' => 'Dr. Synthetic Head',
                'training_duration_years' => 4,
                'approved_resident_slots' => 6,
            ],
            'institution_information' => [
                'bed_capacity' => 250,
                'ownership_type' => 'private',
            ],
            'leadership_staffing' => [
                'program_director' => 'Dr. Synthetic Director',
                'consultant_count' => 8,
            ],
            'facilities_services' => [
                'laboratory_sections' => 'Histopathology, Clinical Chemistry',
                'annual_specimen_volume' => 15000,
            ],
            'curriculum_training' => [
                'curriculum_summary' => 'Synthetic curriculum summary.',
                'rotation_schedule' => 'Synthetic rotation schedule.',
            ],
        ];
    }

    /**
     * Build a draft that satisfies every submission precondition.
     */
    protected function submittableDraft(User $user, Program $program): AccreditationApplication
    {
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->withCompletedSections()
            ->create(['created_by' => $user->id]);

        app(ChecklistResponseSynchronizer::class)->sync($application);

        // Answer every mandatory requirement.
        $application->refresh()->requirements()->with('requirement')->get()
            ->filter(fn ($row): bool => (bool) $row->requirement?->is_mandatory)
            ->each(fn ($row) => $row->update([
                'applicant_status' => ApplicantResponseStatus::Complete,
                'applicant_response' => 'Synthetic response.',
                'responded_at' => now(),
                'responded_by' => $user->id,
            ]));

        $application->forceFill([
            'declaration_accepted_at' => now(),
            'declaration_accepted_by' => $user->id,
        ])->save();

        return $application->refresh();
    }
}
