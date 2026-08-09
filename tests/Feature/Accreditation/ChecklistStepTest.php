<?php

namespace Tests\Feature\Accreditation;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ProgramType;
use App\Enums\ReviewerResponseStatus;
use App\Enums\RoleName;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistVersion;
use App\Models\Institution;
use App\Services\Accreditation\ChecklistResponseSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\Concerns\CreatesWizardData;
use Tests\TestCase;

/**
 * Requirements 6, 7: the checklist step.
 */
class ChecklistStepTest extends TestCase
{
    use CreatesFoundationData, CreatesWizardData, RefreshDatabase;

    public function test_wizard_displays_applicable_checklist_requirements(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $checklist = $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id, 'current_step' => WizardStep::ChecklistResponses]);

        $this->actingAs($user)
            ->get(route('applications.wizard.edit', $application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('accreditation/applications/wizard')
                ->has('checklist', 1)
                // The reviewer-only requirement is withheld from the applicant.
                ->has('checklist.0.items', 2)
                ->where('checklist.0.items.0.code', $checklist['mandatory']->code)
                ->where('checklist.0.items.0.is_mandatory', true)
                ->where('checklist.0.items.0.severity_label', $checklist['mandatory']->severity->label())
            );

        // Response rows were materialised for the answerable requirements only.
        $this->assertDatabaseHas('application_requirements', [
            'accreditation_application_id' => $application->id,
            'checklist_requirement_id' => $checklist['mandatory']->id,
        ]);

        $this->assertDatabaseMissing('application_requirements', [
            'accreditation_application_id' => $application->id,
            'checklist_requirement_id' => $checklist['reviewerOnly']->id,
        ]);
    }

    public function test_requirements_for_other_program_types_are_excluded(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution); // CombinedPathology
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $this->synthChecklist();

        $anatomicOnly = ChecklistRequirement::factory()->create([
            'applicable_program_types' => [ProgramType::AnatomicPathology->value],
        ]);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        app(ChecklistResponseSynchronizer::class)->sync($application);

        $this->assertDatabaseMissing('application_requirements', [
            'accreditation_application_id' => $application->id,
            'checklist_requirement_id' => $anatomicOnly->id,
        ]);
    }

    public function test_not_applicable_requires_an_explanation(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        app(ChecklistResponseSynchronizer::class)->sync($application);
        $row = $application->refresh()->requirements()->firstOrFail();

        // Without an explanation: rejected.
        $this->actingAs($user)
            ->post(route('applications.wizard.checklist', $application), [
                'requirement_id' => $row->id,
                'applicant_status' => ApplicantResponseStatus::NotApplicable->value,
                'applicant_response' => 'Not relevant to this program.',
                'applicant_explanation' => '',
            ])
            ->assertSessionHasErrors('applicant_explanation');

        $this->assertNull($row->refresh()->applicant_explanation);

        // With an explanation: accepted.
        $this->actingAs($user)
            ->post(route('applications.wizard.checklist', $application), [
                'requirement_id' => $row->id,
                'applicant_status' => ApplicantResponseStatus::NotApplicable->value,
                'applicant_response' => 'Not relevant to this program.',
                'applicant_explanation' => 'This institution does not operate that service line.',
            ])
            ->assertSessionHasNoErrors();

        $row->refresh();

        $this->assertSame(ApplicantResponseStatus::NotApplicable, $row->applicant_status);
        $this->assertSame('This institution does not operate that service line.', $row->applicant_explanation);
        $this->assertSame($user->id, $row->responded_by);
        $this->assertNotNull($row->responded_at);
    }

    public function test_applicant_cannot_write_reviewer_status(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        app(ChecklistResponseSynchronizer::class)->sync($application);
        $row = $application->refresh()->requirements()->firstOrFail();

        $this->actingAs($user)
            ->post(route('applications.wizard.checklist', $application), [
                'requirement_id' => $row->id,
                'applicant_status' => ApplicantResponseStatus::Complete->value,
                'applicant_response' => 'Done.',
                // Attempt to smuggle a reviewer verdict through the applicant form.
                'reviewer_status' => 'accepted',
            ])
            ->assertSessionHasNoErrors();

        // Reviewer status stays at its default: applicant data never becomes acceptance.
        $this->assertSame(ReviewerResponseStatus::Pending, $row->refresh()->reviewer_status);
    }

    public function test_applicant_cannot_answer_a_reviewer_only_requirement(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $checklist = $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        // Force-create a row for the reviewer-only requirement.
        $row = $application->requirements()->create([
            'checklist_requirement_id' => $checklist['reviewerOnly']->id,
        ]);

        $this->actingAs($user)
            ->post(route('applications.wizard.checklist', $application), [
                'requirement_id' => $row->id,
                'applicant_status' => ApplicantResponseStatus::Complete->value,
                'applicant_response' => 'Attempting to self-accept.',
            ])
            ->assertSessionHasErrors('requirement_id');
    }

    public function test_a_requirement_from_another_application_is_rejected(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $mine = AccreditationApplication::factory()->forProgram($program)->create(['created_by' => $user->id]);
        // A second institution: one program per type per institution is enforced.
        $other = AccreditationApplication::factory()
            ->forProgram($this->activeProgram(Institution::factory()->create()))
            ->create();

        app(ChecklistResponseSynchronizer::class)->sync($mine);
        app(ChecklistResponseSynchronizer::class)->sync($other);

        $foreignRow = $other->refresh()->requirements()->firstOrFail();

        $this->actingAs($user)
            ->post(route('applications.wizard.checklist', $mine), [
                'requirement_id' => $foreignRow->id,
                'applicant_status' => ApplicantResponseStatus::Complete->value,
                'applicant_response' => 'Cross-application write attempt.',
            ])
            ->assertSessionHasErrors('requirement_id');
    }

    public function test_checklist_version_is_frozen_on_the_application(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $checklist = $this->synthChecklist();

        $published = ChecklistVersion::findOrFail($checklist['mandatory']->checklist_version_id);

        $this->actingAs($user)->post(route('applications.store'), [
            'program_id' => $program->id,
            'application_type' => 'new',
        ]);

        $application = AccreditationApplication::firstOrFail();
        $this->assertSame($published->version_code, $application->checklist_version);
        $this->assertSame($published->id, $application->checklist_version_id);

        // Changing the configured version later must not alter the stored one.
        config(['accreditation.checklist_version' => '2027.0']);

        $this->assertSame($published->version_code, $application->refresh()->checklist_version);
    }
}
