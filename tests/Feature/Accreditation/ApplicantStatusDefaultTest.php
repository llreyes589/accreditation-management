<?php

namespace Tests\Feature\Accreditation;

use App\Enums\ApplicantResponseStatus;
use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Models\ChecklistRequirement;
use App\Models\Institution;
use App\Services\Accreditation\ChecklistResponseSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesFoundationData;
use Tests\Concerns\CreatesWizardData;
use Tests\TestCase;

/**
 * Regression cover for the double-quoted applicant_status default.
 *
 * A renameColumn() on MySQL re-quoted the column's inherited default, so rows
 * were written with the literal "'not_started'" (apostrophes included) and the
 * enum cast threw when the wizard loaded them.
 *
 * These assertions are behavioural rather than schema-introspecting: SQLite
 * reports its default already wrapped in apostrophes as a matter of course, so
 * the reported string is indistinguishable from the corrupted MySQL one.
 * Casting a real row is the check that actually discriminates.
 */
class ApplicantStatusDefaultTest extends TestCase
{
    use CreatesFoundationData, CreatesWizardData, RefreshDatabase;

    public function test_a_row_created_with_the_database_default_casts_to_the_enum(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()->forProgram($program)->create();
        $requirementId = ChecklistRequirement::query()->value('id');

        // Insert bypassing the model so the database default is what lands in
        // the column, rather than a value supplied by Eloquent.
        DB::table('application_requirements')->insert([
            'accreditation_application_id' => $application->id,
            'checklist_requirement_id' => $requirementId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $row = ApplicationRequirement::query()->latest('id')->firstOrFail();

        // Reading this property is what threw the original error.
        $this->assertSame(ApplicantResponseStatus::NotStarted, $row->applicant_status);
    }

    public function test_every_enum_case_survives_a_database_round_trip(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()->forProgram($program)->create();
        app(ChecklistResponseSynchronizer::class)->sync($application);

        $row = $application->refresh()->requirements()->firstOrFail();

        foreach (ApplicantResponseStatus::cases() as $case) {
            $row->forceFill(['applicant_status' => $case])->save();

            $stored = DB::table('application_requirements')
                ->where('id', $row->id)
                ->value('applicant_status');

            $this->assertSame(
                $case->value,
                $stored,
                "Stored value for {$case->name} must be the bare backing value.",
            );

            $this->assertSame($case, $row->fresh()->applicant_status);
        }
    }

    public function test_the_wizard_loads_freshly_synced_requirements_without_a_cast_error(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        app(ChecklistResponseSynchronizer::class)->sync($application);

        // This is the request that surfaced the original error.
        $this->actingAs($user)
            ->get(route('applications.wizard.edit', $application))
            ->assertOk();

        $this->assertTrue(
            $application->refresh()->requirements->every(
                fn (ApplicationRequirement $r): bool => $r->applicant_status instanceof ApplicantResponseStatus,
            ),
        );
    }
}
