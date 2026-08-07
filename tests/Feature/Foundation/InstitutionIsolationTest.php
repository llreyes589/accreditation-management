<?php

namespace Tests\Feature\Foundation;

use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\Institution;
use App\Models\Program;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\TestCase;

class InstitutionIsolationTest extends TestCase
{
    use CreatesFoundationData, RefreshDatabase;

    public function test_institution_user_cannot_view_another_institution(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();

        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $own);

        $this->assertTrue($user->can('view', $own));
        $this->assertFalse($user->can('view', $other));
    }

    public function test_institution_user_cannot_view_another_institutions_program(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();
        $ownProgram = Program::factory()->for($own)->create();
        $otherProgram = Program::factory()->for($other)->create();

        $user = $this->userWithRole(RoleName::TrainingOfficer, $own);

        $this->assertTrue($user->can('view', $ownProgram));
        $this->assertFalse($user->can('view', $otherProgram));
    }

    public function test_institution_user_cannot_view_another_institutions_application(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();

        $ownApplication = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for($own)->create())
            ->create();
        $otherApplication = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for($other)->create())
            ->create();

        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $own);

        $this->assertTrue($user->can('view', $ownApplication));
        $this->assertFalse($user->can('view', $otherApplication));
    }

    public function test_institution_user_cannot_update_another_institutions_application(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();

        $otherApplication = AccreditationApplication::factory()
            ->forProgram(Program::factory()->for($other)->create())
            ->create();

        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $own);

        $this->assertFalse($user->can('update', $otherApplication));
    }

    public function test_application_query_scope_filters_by_institution(): void
    {
        $own = Institution::factory()->create();
        $other = Institution::factory()->create();

        AccreditationApplication::factory()
            ->forProgram(Program::factory()->for($own)->create())
            ->create();
        AccreditationApplication::factory()
            ->forProgram(Program::factory()->for($other)->create())
            ->create();

        $this->assertSame(1, AccreditationApplication::forInstitution($own->id)->count());
        $this->assertSame(2, AccreditationApplication::query()->count());
    }
}
