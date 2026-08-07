<?php

namespace Tests\Feature\Accreditation;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\Concerns\CreatesWizardData;
use Tests\TestCase;

/**
 * Requirements 1, 2, 3, 12: starting an application.
 */
class StartApplicationTest extends TestCase
{
    use CreatesFoundationData, CreatesWizardData, RefreshDatabase;

    public function test_authenticated_institution_user_can_start_an_application(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $response = $this->actingAs($user)->post(route('applications.store'), [
            'program_id' => $program->id,
            'application_type' => ApplicationType::New->value,
        ]);

        $application = AccreditationApplication::firstOrFail();

        $response->assertRedirect(route('applications.wizard.edit', $application));

        // Persisted to the database immediately, not held in session.
        $this->assertDatabaseHas('accreditation_applications', [
            'id' => $application->id,
            'program_id' => $program->id,
            'institution_id' => $institution->id,
            'application_type' => ApplicationType::New->value,
            'status' => ApplicationStatus::Draft->value,
            'created_by' => $user->id,
        ]);

        $this->assertNotNull($application->application_reference);
        $this->assertNotNull($application->checklist_version);
    }

    public function test_unauthenticated_user_cannot_access_the_wizard(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $application = AccreditationApplication::factory()->forProgram($program)->create();

        $this->get(route('applications.index'))->assertRedirect(route('login'));
        $this->get(route('applications.create'))->assertRedirect(route('login'));
        $this->get(route('applications.wizard.edit', $application))->assertRedirect(route('login'));
        $this->post(route('applications.store'))->assertRedirect(route('login'));
    }

    public function test_user_cannot_select_another_institutions_program(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();
        $foreignProgram = $this->activeProgram($other);

        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $own);

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'program_id' => $foreignProgram->id,
                'application_type' => ApplicationType::New->value,
            ])
            ->assertSessionHasErrors('program_id');

        $this->assertDatabaseCount('accreditation_applications', 0);
    }

    public function test_start_page_only_lists_programs_from_the_users_institution(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();

        $ownProgram = $this->activeProgram($own);
        $this->activeProgram($other);

        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $own);

        $this->actingAs($user)
            ->get(route('applications.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('accreditation/applications/create')
                ->has('programs', 1)
                ->where('programs.0.id', $ownProgram->id)
            );
    }

    public function test_residents_cannot_create_applications(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $resident = $this->userWithRole(RoleName::Resident, $institution);

        $this->actingAs($resident)
            ->get(route('applications.create'))
            ->assertForbidden();

        $this->actingAs($resident)
            ->post(route('applications.store'), [
                'program_id' => $program->id,
                'application_type' => ApplicationType::New->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('accreditation_applications', 0);
    }

    public function test_consultants_cannot_create_applications(): void
    {
        $institution = Institution::factory()->create();
        $consultant = $this->userWithRole(RoleName::Consultant, $institution);

        $this->actingAs($consultant)
            ->get(route('applications.create'))
            ->assertForbidden();
    }

    public function test_duplicate_active_application_is_rejected(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        AccreditationApplication::factory()
            ->forProgram($program)
            ->type(ApplicationType::New)
            ->create();

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'program_id' => $program->id,
                'application_type' => ApplicationType::New->value,
            ])
            ->assertSessionHasErrors('program_id');

        $this->assertDatabaseCount('accreditation_applications', 1);
    }

    public function test_a_withdrawn_application_does_not_block_a_new_one(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        AccreditationApplication::factory()
            ->forProgram($program)
            ->type(ApplicationType::New)
            ->status(ApplicationStatus::Withdrawn)
            ->create();

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'program_id' => $program->id,
                'application_type' => ApplicationType::New->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('accreditation_applications', 2);
    }

    public function test_a_different_application_type_is_not_a_duplicate(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        AccreditationApplication::factory()
            ->forProgram($program)
            ->type(ApplicationType::New)
            ->create();

        $this->actingAs($user)
            ->post(route('applications.store'), [
                'program_id' => $program->id,
                'application_type' => ApplicationType::Renewal->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('accreditation_applications', 2);
    }
}
