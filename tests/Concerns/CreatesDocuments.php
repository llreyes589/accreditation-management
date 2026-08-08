<?php

namespace Tests\Concerns;

use App\Enums\ApplicationStatus;
use App\Enums\ProgramType;
use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use App\Models\Institution;
use App\Models\Program;
use App\Models\User;
use App\Services\Accreditation\ChecklistResponseSynchronizer;
use App\Services\ChecklistVersionService;

/**
 * Builds the fixtures the supporting-document tests need: an institution, a
 * program, a draft application frozen to a published checklist version that
 * contains at least one requirement flagged requires_document.
 */
trait CreatesDocuments
{
    /**
     * Build a synthetic published checklist version with one mandatory and one
     * optional requirement that require a supporting document.
     */
    protected function documentChecklist(ProgramType $programType = ProgramType::CombinedPathology): ChecklistVersion
    {
        $version = ChecklistVersion::factory()->published()->create([
            'program_type' => $programType->value,
        ]);

        $section = ChecklistSection::factory()->create([
            'checklist_version_id' => $version->id,
            'code' => 'DOC-SEC',
        ]);

        ChecklistRequirement::factory()->create([
            'checklist_version_id' => $version->id,
            'checklist_section_id' => $section->id,
            'code' => 'DOC-REQ',
            'requirement' => '[SYNTHETIC] Supporting policy document',
            'requires_document' => true,
            'is_mandatory' => true,
            'allows_multiple_documents' => false,
        ]);

        ChecklistRequirement::factory()->create([
            'checklist_version_id' => $version->id,
            'checklist_section_id' => $section->id,
            'code' => 'OPT-REQ',
            'requirement' => '[SYNTHETIC] Optional supporting document',
            'requires_document' => true,
            'is_mandatory' => false,
            'allows_multiple_documents' => false,
        ]);

        return $version;
    }

    /**
     * Build a draft application for an institution the given user belongs to,
     * frozen to a document-requiring checklist version.
     */
    protected function documentApplication(User $applicant, ProgramType $programType = ProgramType::CombinedPathology): AccreditationApplication
    {
        $institution = Institution::factory()->create();
        $applicant->institutions()->attach($institution->id);
        $applicant->load('institutions'); // refresh cached relation for policy checks

        $program = Program::factory()->create([
            'institution_id' => $institution->id,
            'program_type' => $programType->value,
        ]);

        $version = $this->documentChecklist($programType);

        $application = AccreditationApplication::factory()->create([
            'institution_id' => $institution->id,
            'program_id' => $program->id,
            'checklist_version_id' => $version->id,
            'status' => ApplicationStatus::Draft,
        ]);

        (new ChecklistResponseSynchronizer(
            app(ChecklistVersionService::class),
        ))->sync($application);

        return $application;
    }

    protected function documentReviewer(): User
    {
        return $this->userWithRole(RoleName::CartReviewer);
    }

    protected function documentApplicant(): User
    {
        return $this->userWithRole(RoleName::InstitutionAdministrator);
    }
}
