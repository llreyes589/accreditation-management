<?php

namespace Tests\Feature\Foundation;

use App\Enums\RoleName;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\TestCase;

class ChecklistAuthorizationTest extends TestCase
{
    use CreatesFoundationData, RefreshDatabase;

    public function test_psp_administrator_can_configure_checklist_requirements(): void
    {
        $requirement = ChecklistRequirement::factory()->create();
        $pspAdmin = $this->userWithRole(RoleName::PspAdministrator);

        $this->assertTrue($pspAdmin->can('create', ChecklistRequirement::class));
        $this->assertTrue($pspAdmin->can('update', $requirement));
        $this->assertTrue($pspAdmin->can('update', $requirement->section));
    }

    public function test_institution_administrator_cannot_edit_checklist_requirements(): void
    {
        $institution = Institution::factory()->create();
        $requirement = ChecklistRequirement::factory()->create();

        $admin = $this->userWithRole(RoleName::InstitutionAdministrator, $institution);

        $this->assertFalse($admin->can('create', ChecklistRequirement::class));
        $this->assertFalse($admin->can('update', $requirement));
        $this->assertFalse($admin->can('delete', $requirement));
    }

    public function test_training_officer_and_inspector_cannot_edit_checklist_requirements(): void
    {
        $institution = Institution::factory()->create();
        $requirement = ChecklistRequirement::factory()->create();

        $officer = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $inspector = $this->userWithRole(RoleName::Inspector);

        $this->assertFalse($officer->can('update', $requirement));
        $this->assertFalse($inspector->can('update', $requirement));
    }

    public function test_any_authenticated_user_may_read_the_checklist(): void
    {
        $section = ChecklistSection::factory()->create();
        $requirement = ChecklistRequirement::factory()->for($section, 'section')->create();

        $officer = $this->userWithRole(RoleName::TrainingOfficer, Institution::factory()->create());

        $this->assertTrue($officer->can('view', $section));
        $this->assertTrue($officer->can('view', $requirement));
    }
}
