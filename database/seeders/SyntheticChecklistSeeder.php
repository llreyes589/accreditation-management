<?php

namespace Database\Seeders;

use App\Enums\ProgramType;
use App\Enums\RequirementResponseType;
use App\Enums\RequirementSeverity;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * ============================================================================
 * SYNTHETIC PLACEHOLDER CHECKLIST — NOT OFFICIAL PSP ACCREDITATION CRITERIA
 * ============================================================================
 *
 * Every section and requirement below is invented sample content whose only
 * purpose is to exercise the wizard end to end in local development. None of
 * it reflects real Philippine Society of Pathologists accreditation standards.
 *
 * Replace wholesale once the authoritative checklist is supplied. Deleting
 * this seeder breaks no application code: the wizard reads whatever the
 * checklist tables contain.
 *
 * When the real content lands, create a new checklist version from this seed
 * data and publish it; in-flight applications keep the edition they were
 * created against.
 */
class SyntheticChecklistSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Marker prefix so synthetic rows are trivially identifiable and removable.
     */
    private const CODE_PREFIX = 'SYN';

    public function run(): void
    {
        // One published synthetic version per program type so every program
        // type has an applicable checklist to start applications against.
        foreach (ProgramType::cases() as $programType) {
            $version = ChecklistVersion::firstOrCreate(
                ['version_code' => self::CODE_PREFIX.'-'.$programType->value],
                [
                    'name' => 'Synthetic Checklist ('.ucfirst(str_replace('_', ' ', $programType->value)).')',
                    'description' => '[SYNTHETIC PLACEHOLDER] Demonstration checklist.',
                    'program_type' => $programType->value,
                    'status' => 'published',
                    'published_at' => now(),
                ],
            );

            foreach ($this->sections() as $position => $section) {
                $sectionModel = ChecklistSection::updateOrCreate(
                    [
                        'checklist_version_id' => $version->id,
                        'code' => self::CODE_PREFIX.'-'.$section['code'],
                    ],
                    [
                        'title' => $section['title'],
                        'description' => '[SYNTHETIC PLACEHOLDER] '.$section['description'],
                        'position' => $position,
                        'is_active' => true,
                    ],
                );

                foreach ($section['requirements'] as $index => $requirement) {
                    ChecklistRequirement::updateOrCreate(
                        [
                            'checklist_version_id' => $version->id,
                            'code' => self::CODE_PREFIX.'-'.$requirement['code'],
                        ],
                        [
                            'checklist_section_id' => $sectionModel->id,
                            'requirement' => '[SYNTHETIC] '.$requirement['text'],
                            'guidance' => $requirement['guidance'] ?? null,
                            'response_type' => $requirement['response_type'] ?? RequirementResponseType::Text->value,
                            'severity' => $requirement['severity'],
                            'weight' => $requirement['weight'] ?? 1,
                            'pass_threshold' => null,
                            'is_mandatory' => $requirement['mandatory'] ?? false,
                            'is_reviewer_only' => $requirement['reviewer_only'] ?? false,
                            'requires_document' => $requirement['requires_document'] ?? false,
                            'applicable_program_types' => $requirement['program_types'] ?? null,
                            'position' => $index,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sections(): array
    {
        return [
            [
                'code' => 'GOV',
                'title' => 'Governance and Administration',
                'description' => 'Sample governance items for wizard testing.',
                'requirements' => [
                    [
                        'code' => 'GOV-01',
                        'text' => 'The institution has a written organizational chart for the training program.',
                        'guidance' => 'Placeholder guidance text for development only.',
                        'severity' => RequirementSeverity::Major,
                        'mandatory' => true,
                    ],
                    [
                        'code' => 'GOV-02',
                        'text' => 'A designated program director is formally appointed in writing.',
                        'severity' => RequirementSeverity::Major,
                        'mandatory' => true,
                    ],
                    [
                        'code' => 'GOV-03',
                        'text' => 'The program maintains minutes of regular faculty meetings.',
                        'severity' => RequirementSeverity::Minor,
                    ],
                    [
                        'code' => 'GOV-04',
                        'text' => 'Reviewer verification of governance documentation completeness.',
                        'severity' => RequirementSeverity::Informational,
                        'reviewer_only' => true,
                        'response_type' => RequirementResponseType::Acknowledgement->value,
                    ],
                ],
            ],
            [
                'code' => 'FAC',
                'title' => 'Facilities and Laboratory Services',
                'description' => 'Sample facilities items for wizard testing.',
                'requirements' => [
                    [
                        'code' => 'FAC-01',
                        'text' => 'The laboratory maintains dedicated space for resident work areas.',
                        'severity' => RequirementSeverity::Major,
                        'mandatory' => true,
                    ],
                    [
                        'code' => 'FAC-02',
                        'text' => 'Equipment maintenance records are available for the current cycle.',
                        'severity' => RequirementSeverity::Minor,
                    ],
                    [
                        'code' => 'FAC-03',
                        'text' => 'A functioning frozen-section facility is available on site.',
                        'guidance' => 'Applies to programs with an anatomic pathology component.',
                        'severity' => RequirementSeverity::Major,
                        'program_types' => [
                            ProgramType::AnatomicPathology->value,
                            ProgramType::CombinedPathology->value,
                        ],
                    ],
                    [
                        'code' => 'FAC-04',
                        'text' => 'Clinical chemistry analyzers are covered by a current calibration schedule.',
                        'severity' => RequirementSeverity::Minor,
                        'program_types' => [
                            ProgramType::ClinicalPathology->value,
                            ProgramType::CombinedPathology->value,
                        ],
                    ],
                ],
            ],
            [
                'code' => 'CUR',
                'title' => 'Curriculum and Training',
                'description' => 'Sample curriculum items for wizard testing.',
                'requirements' => [
                    [
                        'code' => 'CUR-01',
                        'text' => 'A written curriculum covering the full training duration is available.',
                        'severity' => RequirementSeverity::Major,
                        'mandatory' => true,
                    ],
                    [
                        'code' => 'CUR-02',
                        'text' => 'Resident rotation schedules are documented for the current academic year.',
                        'severity' => RequirementSeverity::Minor,
                    ],
                    [
                        'code' => 'CUR-03',
                        'text' => 'The program conducts documented periodic resident evaluations.',
                        'severity' => RequirementSeverity::Major,
                        'mandatory' => true,
                    ],
                    [
                        'code' => 'CUR-04',
                        'text' => 'A journal club or equivalent academic activity is held regularly.',
                        'severity' => RequirementSeverity::Informational,
                    ],
                ],
            ],
        ];
    }
}
