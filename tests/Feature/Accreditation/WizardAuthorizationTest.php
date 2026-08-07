<?php

namespace Tests\Feature\Accreditation;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;
use App\Models\Institution;
use App\Services\Accreditation\ChecklistResponseSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\Concerns\CreatesWizardData;
use Tests\TestCase;

/**
 * Requirement 14 and the authorization matrix.
 *
 * Every check here is a server-side assertion: hidden buttons are irrelevant.
 */
class WizardAuthorizationTest extends TestCase
{
    use CreatesFoundationData, CreatesWizardData, RefreshDatabase;

    public function test_user_cannot_access_another_institutions_application_by_url(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();
        $this->synthChecklist();

        $foreign = AccreditationApplication::factory()
            ->forProgram($this->activeProgram($other))
            ->create();

        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $own);

        // Every route bound to the application must refuse.
        $this->actingAs($user)
            ->get(route('applications.wizard.edit', $foreign))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('applications.confirmation', $foreign))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('applications.wizard.step', $foreign), [
                'step' => WizardStep::LeadershipStaffing->value,
                'payload' => ['program_director' => 'X', 'consultant_count' => 1],
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('applications.wizard.navigate', $foreign), ['step' => WizardStep::first()->value])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('applications.wizard.declaration', $foreign), ['declaration_accepted' => true])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('applications.submit', $foreign))
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('applications.withdraw', $foreign))
            ->assertForbidden();
    }

    public function test_application_list_excludes_other_institutions(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();

        $mine = AccreditationApplication::factory()->forProgram($this->activeProgram($own))->create();
        AccreditationApplication::factory()->forProgram($this->activeProgram($other))->create();

        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $own);

        $this->actingAs($user)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('accreditation/applications/index')
                ->has('applications', 1)
                ->where('applications.0.id', $mine->id)
            );
    }

    public function test_psp_administrator_can_view_applications_across_institutions(): void
    {
        $institution = Institution::factory()->create();
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($this->activeProgram($institution))
            ->status(ApplicationStatus::Submitted)
            ->create();

        $admin = $this->userWithRole(RoleName::PspAdministrator);

        $this->actingAs($admin)
            ->get(route('applications.wizard.edit', $application))
            ->assertOk();

        $this->actingAs($admin)
            ->get(route('applications.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('applications', 1));
    }

    public function test_reviewers_cannot_impersonate_the_applicant(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $owner = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $owner->id]);

        app(ChecklistResponseSynchronizer::class)->sync($application);
        $row = $application->refresh()->requirements()->firstOrFail();

        foreach ([RoleName::PspAdministrator, RoleName::CartReviewer, RoleName::Inspector] as $role) {
            $reviewer = $this->userWithRole($role);

            // Viewing may be allowed, but writing applicant data never is.
            $this->actingAs($reviewer)
                ->post(route('applications.wizard.step', $application), [
                    'step' => WizardStep::LeadershipStaffing->value,
                    'payload' => ['program_director' => 'Reviewer edit', 'consultant_count' => 3],
                ])
                ->assertForbidden();

            $this->actingAs($reviewer)
                ->post(route('applications.wizard.checklist', $application), [
                    'requirement_id' => $row->id,
                    'applicant_status' => ApplicantResponseStatus::Complete->value,
                    'applicant_response' => 'Reviewer answering for applicant.',
                ])
                ->assertForbidden();

            $this->actingAs($reviewer)
                ->post(route('applications.submit', $application))
                ->assertForbidden();

            $this->actingAs($reviewer)
                ->post(route('applications.withdraw', $application))
                ->assertForbidden();
        }

        $this->assertNull($application->refresh()->leadership_staffing);
    }

    public function test_residents_cannot_edit_applications(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()->forProgram($program)->create();
        $resident = $this->userWithRole(RoleName::Resident, $institution);

        $this->actingAs($resident)
            ->post(route('applications.wizard.step', $application), [
                'step' => WizardStep::LeadershipStaffing->value,
                'payload' => ['program_director' => 'Resident edit', 'consultant_count' => 2],
            ])
            ->assertForbidden();

        $this->actingAs($resident)
            ->post(route('applications.withdraw', $application))
            ->assertForbidden();
    }

    public function test_consultants_cannot_edit_applications(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()->forProgram($program)->create();
        $consultant = $this->userWithRole(RoleName::Consultant, $institution);

        $this->actingAs($consultant)
            ->post(route('applications.wizard.step', $application), [
                'step' => WizardStep::LeadershipStaffing->value,
                'payload' => ['program_director' => 'Consultant edit', 'consultant_count' => 2],
            ])
            ->assertForbidden();
    }

    public function test_institution_administrator_and_training_officer_can_both_edit(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()->forProgram($program)->create();

        foreach ([RoleName::InstitutionAdministrator, RoleName::TrainingOfficer] as $role) {
            $user = $this->userWithRole($role, $institution);

            $this->actingAs($user)
                ->post(route('applications.wizard.step', $application), [
                    'step' => WizardStep::LeadershipStaffing->value,
                    'payload' => ['program_director' => 'Dr. Allowed', 'consultant_count' => 4],
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame('Dr. Allowed', $application->refresh()->leadership_staffing['program_director']);
    }
}
