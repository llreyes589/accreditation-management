<?php

namespace Tests\Concerns;

use App\Enums\RequirementResponseType;
use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\Institution;
use App\Models\User;
use App\Services\Accreditation\ApplicationWizardService;
use App\Services\Accreditation\ChecklistResponseSynchronizer;
use Illuminate\Support\Facades\Storage;

/**
 * Synthetic fixtures for the completeness-review workflow.
 *
 * No real institution or PSP criteria are used; all data is fictional.
 */
trait CreatesCompletenessData
{
    use CreatesDocuments;
    use CreatesFoundationData;
    use CreatesWizardData;

    /**
     * An institution with an applicant and a PSP/CART reviewer.
     *
     * @return array{institution: Institution, applicant: User, reviewer: User}
     */
    protected function completenessActors(): array
    {
        $institution = Institution::factory()->create();

        $applicant = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $reviewer = $this->userWithRole(RoleName::CartReviewer, $institution);

        return ['institution' => $institution, 'applicant' => $applicant, 'reviewer' => $reviewer];
    }

    /**
     * A PSP/CART reviewer who belongs to NO institution (cross-institution).
     */
    protected function crossInstitutionReviewer(): User
    {
        return $this->userWithRole(RoleName::CartReviewer);
    }

    /**
     * Attach a document-requiring checklist requirement to an application's
     * frozen checklist version so completeness can be exercised with a real
     * deficiency, then re-sync the application requirements.
     */
    protected function addDocumentRequirement(AccreditationApplication $application): ApplicationRequirement
    {
        // Reuse the checklist version the application was already synced to,
        // so existing mandatory checklist responses are preserved.
        $existing = $application->requirements()->with('requirement')->firstOrFail();
        $version = $existing->requirement->version;

        if ($application->checklist_version_id !== $version->id) {
            $application->forceFill(['checklist_version_id' => $version->id])->save();
        }

        $section = ChecklistSection::where('checklist_version_id', $version->id)->first()
            ?? ChecklistSection::factory()->create([
                'checklist_version_id' => $version->id,
                'title' => '[SYNTHETIC] Document section',
                'position' => 0,
            ]);

        $requirement = ChecklistRequirement::factory()->for($section, 'section')->create([
            'checklist_version_id' => $version->id,
            'code' => 'SYN-DOC',
            'requirement' => '[SYNTHETIC] Quality manual (required document).',
            'requires_document' => true,
            'response_type' => RequirementResponseType::Text,
            'position' => 99,
        ]);

        app(ChecklistResponseSynchronizer::class)->sync($application);

        return $application->refresh()
            ->requirements()
            ->where('checklist_requirement_id', $requirement->id)
            ->firstOrFail();
    }

    /**
     * A submitted application that has satisfied its mandatory checklist
     * requirements (so the only remaining completeness factor is documents).
     *
     * @return array{application: AccreditationApplication, applicant: User, reviewer: User, institution: Institution}
     */
    protected function submittedApplication(): array
    {
        Storage::fake('accreditation');

        $actors = $this->completenessActors();
        $program = $this->activeProgram($actors['institution']);

        $application = $this->submittableDraft($actors['applicant'], $program);
        app(ChecklistResponseSynchronizer::class)->sync($application);
        $application = app(ApplicationWizardService::class)
            ->submit($actors['applicant'], $application);

        return [
            'application' => $application->refresh(),
            'applicant' => $actors['applicant'],
            'reviewer' => $actors['reviewer'],
            'institution' => $actors['institution'],
        ];
    }
}
