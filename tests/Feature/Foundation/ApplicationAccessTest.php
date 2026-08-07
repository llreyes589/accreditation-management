<?php

namespace Tests\Feature\Foundation;

use App\Enums\ApplicationStatus;
use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\Institution;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\TestCase;

class ApplicationAccessTest extends TestCase
{
    use CreatesFoundationData, RefreshDatabase;

    public function test_psp_administrator_can_view_applications_from_any_institution(): void
    {
        $first = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for(Institution::factory())->create())
            ->create();
        $second = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for(Institution::factory())->create())
            ->create();

        $pspAdmin = $this->userWithRole(RoleName::PspAdministrator);

        $this->assertTrue($pspAdmin->can('view', $first));
        $this->assertTrue($pspAdmin->can('view', $second));
        $this->assertTrue($pspAdmin->can('viewAny', AccreditationApplication::class));
    }

    public function test_psp_administrator_cannot_edit_an_institutions_draft_submission(): void
    {
        $application = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for(Institution::factory())->create())
            ->create();

        $pspAdmin = $this->userWithRole(RoleName::PspAdministrator);

        $this->assertFalse($pspAdmin->can('update', $application));
    }

    public function test_institution_staff_can_edit_their_own_draft_application(): void
    {
        $institution = Institution::factory()->create();
        $application = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for($institution)->create())
            ->create();

        $officer = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $this->assertTrue($officer->can('update', $application));
    }

    public function test_submitted_applications_are_no_longer_editable_by_the_institution(): void
    {
        $institution = Institution::factory()->create();
        $application = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for($institution)->create())
            ->status(ApplicationStatus::Submitted)
            ->create();

        $officer = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $this->assertFalse($officer->can('update', $application));
    }

    public function test_applications_can_never_be_deleted(): void
    {
        $institution = Institution::factory()->create();
        $application = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for($institution)->create())
            ->create();

        $pspAdmin = $this->userWithRole(RoleName::PspAdministrator);
        $admin = $this->userWithRole(RoleName::InstitutionAdministrator, $institution);

        $this->assertFalse($pspAdmin->can('delete', $application));
        $this->assertFalse($admin->can('delete', $application));
    }
}
