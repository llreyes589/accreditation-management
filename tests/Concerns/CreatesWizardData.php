<?php

namespace Tests\Concerns;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
use App\Enums\RequirementResponseType;
use App\Enums\RequirementSeverity;
use App\Models\AccreditationApplication;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
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
        $program = Program::factory()
            ->for($institution ?? Institution::factory())
            ->create([
                'program_type' => ProgramType::CombinedPathology,
                'status' => ProgramStatus::Active,
            ]);

        // Ensure a published checklist version exists for the program type,
        // mirroring the seeded production configuration. Without it the wizard
        // cannot resolve a version to freeze onto the application.
        if (! ChecklistVersion::query()->published()->forProgramType($program->program_type)->exists()) {
            ChecklistVersion::factory()->published()->create([
                'version_code' => 'TEST-'.$program->program_type->value,
                'program_type' => $program->program_type,
            ]);
        }

        return $program;
    }

    /**
     * Create a small synthetic checklist.
     *
     * @return array{section: ChecklistSection, mandatory: ChecklistRequirement, optional: ChecklistRequirement, reviewerOnly: ChecklistRequirement}
     */
    protected function synthChecklist(): array
    {
        $version = ChecklistVersion::factory()->published()->create([
            'version_code' => 'SYN-WIZ',
            'program_type' => ProgramType::CombinedPathology,
        ]);

        $section = ChecklistSection::factory()->create([
            'checklist_version_id' => $version->id,
            'title' => 'Synthetic Section',
            'position' => 0,
        ]);

        return [
            'section' => $section,
            'mandatory' => ChecklistRequirement::factory()->for($section, 'section')->mandatory()->create([
                'checklist_version_id' => $version->id,
                'requirement' => '[SYNTHETIC] Mandatory requirement for testing.',
                'response_type' => RequirementResponseType::Text,
                'position' => 0,
            ]),
            'optional' => ChecklistRequirement::factory()->for($section, 'section')->create([
                'checklist_version_id' => $version->id,
                'requirement' => '[SYNTHETIC] Optional requirement for testing.',
                'severity' => RequirementSeverity::Minor,
                'response_type' => RequirementResponseType::Text,
                'position' => 1,
            ]),
            'reviewerOnly' => ChecklistRequirement::factory()->for($section, 'section')->reviewerOnly()->create([
                'checklist_version_id' => $version->id,
                'requirement' => '[SYNTHETIC] Reviewer-only requirement.',
                'response_type' => RequirementResponseType::Text,
                'position' => 2,
            ]),
        ];
    }

    /**
     * Create a DRAFT checklist version with one section and two requirements,
     * for exercising the administration screens.
     *
     * @return array{version: ChecklistVersion, section: ChecklistSection, requirement: ChecklistRequirement}
     */
    protected function draftChecklist(ProgramType $programType = ProgramType::CombinedPathology): array
    {
        $version = ChecklistVersion::factory()->create([
            'version_code' => 'DRAFT-'.fake()->unique()->numerify('####'),
            'program_type' => $programType,
        ]);

        $section = ChecklistSection::factory()->create([
            'checklist_version_id' => $version->id,
            'title' => 'Draft Section',
            'code' => 'SEC-1',
            'position' => 0,
        ]);

        $requirement = ChecklistRequirement::factory()->for($section, 'section')->create([
            'checklist_version_id' => $version->id,
            'code' => 'REQ-1',
            'requirement' => '[SYNTHETIC] Draft requirement.',
            'response_type' => RequirementResponseType::Text,
            'position' => 0,
        ]);

        return ['version' => $version, 'section' => $section, 'requirement' => $requirement];
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
