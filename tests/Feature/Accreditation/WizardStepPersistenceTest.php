<?php

namespace Tests\Feature\Accreditation;

use App\Enums\RoleName;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\Concerns\CreatesWizardData;
use Tests\TestCase;

/**
 * End-to-end guard for the step-4-and-beyond save failure.
 *
 * The wizard reused a single React form instance across steps, so `step` stayed
 * pinned to whichever step mounted first and later steps silently wrote into
 * the wrong JSON column (presenting as "step 4 onwards saves nothing"). This
 * test drives the full HTTP flow a browser would: create, then walk every data
 * step in turn, asserting each one persists to its own column and survives a
 * subsequent revisit.
 */
class WizardStepPersistenceTest extends TestCase
{
    use CreatesFoundationData, CreatesWizardData, RefreshDatabase;

    private function actingOfficer(): User
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        return $user;
    }

    public function test_walking_all_steps_persists_each_to_its_own_column(): void
    {
        $user = $this->actingOfficer();
        $application = AccreditationApplication::where('created_by', $user->id)->firstOrFail();

        foreach ($this->completeSectionPayloads() as $stepValue => $payload) {
            $this->actingAs($user)
                ->post(route('applications.wizard.step', $application), [
                    'step' => $stepValue,
                    'advance' => false,
                    'payload' => $payload,
                ])
                ->assertSessionHasNoErrors();
        }

        $application->refresh();

        foreach ($this->completeSectionPayloads() as $stepValue => $payload) {
            $column = WizardStep::from($stepValue)->payloadColumn();
            $stored = $application->getAttribute($column) ?? [];

            foreach ($payload as $key => $value) {
                $this->assertSame(
                    (string) $value,
                    (string) ($stored[$key] ?? 'MISSING'),
                    "Step [{$stepValue}] field [{$key}] was not persisted to [{$column}].",
                );
            }
        }
    }

    public function test_revisiting_an_earlier_step_does_not_erase_later_steps(): void
    {
        $user = $this->actingOfficer();
        $application = AccreditationApplication::where('created_by', $user->id)->firstOrFail();

        // Save step 5 first.
        $this->actingAs($user)
            ->post(route('applications.wizard.step', $application), [
                'step' => 'curriculum_training',
                'payload' => [
                    'curriculum_summary' => 'Structured didactic curriculum.',
                    'rotation_schedule' => '12-month rotation.',
                    'evaluation_methods' => 'Continuous assessment.',
                ],
            ])
            ->assertSessionHasNoErrors();

        // Then save step 2.
        $this->actingAs($user)
            ->post(route('applications.wizard.step', $application), [
                'step' => 'application_details',
                'payload' => [
                    'program_head' => 'Dr. Reorder',
                    'training_duration_years' => 4,
                    'approved_resident_slots' => 6,
                ],
            ])
            ->assertSessionHasNoErrors();

        $application->refresh();

        // Step 5's data must still be there.
        $this->assertSame(
            'Continuous assessment.',
            $application->curriculum_training['evaluation_methods'],
        );
        $this->assertSame('Dr. Reorder', $application->program_details['program_head']);
    }
}
