<?php

namespace Tests\Feature\Accreditation;

use App\Enums\RoleName;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\Concerns\CreatesWizardData;
use Tests\TestCase;

/**
 * Guards the step -> payload-column routing.
 *
 * The wizard previously reused a single React form instance across every data
 * step, so `step` stayed pinned to whichever step mounted first and later
 * steps wrote into the wrong JSON column. These assertions pin the server
 * contract each step must satisfy independently.
 */
class WizardStepRoutingTest extends TestCase
{
    use CreatesFoundationData, CreatesWizardData, RefreshDatabase;

    public function test_each_step_writes_only_its_own_payload_column(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        $columns = [];

        foreach ($this->completeSectionPayloads() as $stepValue => $payload) {
            $step = WizardStep::from($stepValue);
            $columns[$step->value] = $step->payloadColumn();

            $this->actingAs($user)
                ->post(route('applications.wizard.step', $application), [
                    'step' => $step->value,
                    'advance' => false,
                    'payload' => $payload,
                ])
                ->assertSessionHasNoErrors();
        }

        $application->refresh();

        // Every distinct step must have landed in a distinct column.
        $this->assertSame(
            count($columns),
            count(array_unique($columns)),
            'Two wizard steps map to the same payload column.',
        );

        // And each column must hold exactly its own step's fields.
        foreach ($this->completeSectionPayloads() as $stepValue => $payload) {
            $column = WizardStep::from($stepValue)->payloadColumn();
            $stored = $application->getAttribute($column) ?? [];

            foreach ($payload as $key => $value) {
                $this->assertArrayHasKey(
                    $key,
                    $stored,
                    "Column [{$column}] is missing [{$key}] from step [{$stepValue}].",
                );
                $this->assertSame((string) $value, (string) $stored[$key]);
            }
        }
    }

    public function test_a_later_step_does_not_overwrite_an_earlier_steps_column(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('applications.wizard.step', $application), [
            'step' => WizardStep::ApplicationDetails->value,
            'payload' => [
                'program_head' => 'Dr. Step Two',
                'training_duration_years' => 4,
                'approved_resident_slots' => 6,
            ],
        ])->assertSessionHasNoErrors();

        // Saving a later step must leave the earlier column untouched.
        $this->actingAs($user)->post(route('applications.wizard.step', $application), [
            'step' => WizardStep::FacilitiesServices->value,
            'payload' => [
                'laboratory_sections' => 'Histopathology',
                'annual_specimen_volume' => 1000,
            ],
        ])->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame('Dr. Step Two', $application->program_details['program_head']);
        $this->assertSame('Histopathology', $application->facilities_services['laboratory_sections']);

        // The facilities fields must NOT have leaked into program_details.
        $this->assertArrayNotHasKey('laboratory_sections', $application->program_details);
    }

    public function test_every_data_step_has_a_distinct_payload_column(): void
    {
        $seen = [];

        foreach (WizardStep::cases() as $step) {
            $column = $step->payloadColumn();

            if ($column === null) {
                continue;
            }

            $this->assertNotContains(
                $column,
                $seen,
                "Payload column [{$column}] is claimed by more than one step.",
            );

            $seen[] = $column;
        }

        // The five free-form sections described by the spec.
        $this->assertCount(5, $seen);
    }
}
