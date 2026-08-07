<?php

namespace Tests\Feature\Foundation;

use App\Enums\RoleName;
use App\Models\Institution;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use CreatesFoundationData, RefreshDatabase;

    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_view_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('dashboard'))->assertOk();
    }

    public function test_dashboard_reports_institution_program_and_application_counts(): void
    {
        $institution = Institution::factory()->create(['name' => 'Demo Teaching Hospital']);
        $program = Program::factory()->for($institution)->create(['name' => 'AP/CP Program']);
        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $institution);

        $program->accreditationApplications()->create([
            'institution_id' => $institution->id,
            'application_reference' => 'APP-TEST-0001',
            'status' => 'draft',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('dashboard')
                ->where('summary.institution.name', 'Demo Teaching Hospital')
                ->where('summary.programs.0.name', 'AP/CP Program')
                ->where('summary.application_count', 1)
                ->where('summary.active_accreditation.is_active', false)
            );
    }

    public function test_dashboard_lists_missing_foundation_data(): void
    {
        $institution = Institution::factory()->create();
        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $institution);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('summary.missing_foundation_data.0', 'No training program has been registered.')
            );
    }

    public function test_dashboard_does_not_count_another_institutions_applications(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();
        $otherProgram = Program::factory()->for($other)->create();

        $otherProgram->accreditationApplications()->create([
            'institution_id' => $other->id,
            'application_reference' => 'APP-OTHER-0001',
            'status' => 'draft',
        ]);

        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $own);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('summary.application_count', 0));
    }
}
