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
 * Requirements 4, 5: draft persistence across every step.
 */
class DraftPersistenceTest extends TestCase
{
    use CreatesFoundationData, CreatesWizardData, RefreshDatabase;

    public function test_user_can_save_a_draft_at_every_step(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        foreach ($this->completeSectionPayloads() as $stepValue => $payload) {
            $step = WizardStep::from($stepValue);

            $this->actingAs($user)
                ->post(route('applications.wizard.step', $application), [
                    'step' => $step->value,
                    'advance' => false,
                    'payload' => $payload,
                ])
                ->assertSessionHasNoErrors();

            $application->refresh();

            $stored = $application->getAttribute($step->payloadColumn());

            foreach ($payload as $key => $value) {
                $this->assertSame(
                    (string) $value,
                    (string) $stored[$key],
                    "Step {$step->value} did not persist {$key}",
                );
            }
        }
    }

    public function test_saved_data_remains_after_the_page_is_reloaded(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('applications.wizard.step', $application), [
            'step' => WizardStep::LeadershipStaffing->value,
            'advance' => false,
            'payload' => [
                'program_director' => 'Dr. Persisted Director',
                'consultant_count' => 12,
            ],
        ])->assertSessionHasNoErrors();

        // Simulate a browser refresh: a brand new request cycle.
        $this->actingAs($user)
            ->get(route('applications.wizard.edit', $application))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('accreditation/applications/wizard')
                ->where('application.sections.leadership_staffing.program_director', 'Dr. Persisted Director')
                ->where('application.sections.leadership_staffing.consultant_count', 12)
            );
    }

    public function test_navigating_backward_does_not_lose_saved_data(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        // Save step 3, advance, then navigate back to step 3.
        $this->actingAs($user)->post(route('applications.wizard.step', $application), [
            'step' => WizardStep::InstitutionInformation->value,
            'advance' => true,
            'payload' => ['bed_capacity' => 500, 'ownership_type' => 'government'],
        ]);

        $this->actingAs($user)->post(route('applications.wizard.navigate', $application), [
            'step' => WizardStep::InstitutionInformation->value,
        ])->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame(WizardStep::InstitutionInformation, $application->current_step);
        $this->assertSame(500, (int) $application->institution_information['bed_capacity']);
        $this->assertSame('government', $application->institution_information['ownership_type']);
    }

    public function test_partial_saves_of_the_same_step_are_merged_not_overwritten(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        $this->actingAs($user)->post(route('applications.wizard.step', $application), [
            'step' => WizardStep::CurriculumTraining->value,
            'payload' => [
                'curriculum_summary' => 'First summary.',
                'rotation_schedule' => 'First schedule.',
                'evaluation_methods' => 'Written and practical.',
            ],
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('applications.wizard.step', $application), [
            'step' => WizardStep::CurriculumTraining->value,
            'payload' => [
                'curriculum_summary' => 'Revised summary.',
                'rotation_schedule' => 'First schedule.',
            ],
        ])->assertSessionHasNoErrors();

        $application->refresh();

        $this->assertSame('Revised summary.', $application->curriculum_training['curriculum_summary']);
        // The optional field from the first save survives.
        $this->assertSame('Written and practical.', $application->curriculum_training['evaluation_methods']);
    }

    public function test_step_validation_rejects_incomplete_data(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.wizard.step', $application), [
                'step' => WizardStep::LeadershipStaffing->value,
                'payload' => ['program_director' => ''],
            ])
            ->assertSessionHasErrors(['payload.program_director', 'payload.consultant_count']);
    }

    public function test_completion_percentage_is_recalculated_from_persisted_data(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id, 'completion_percentage' => 0]);

        $this->actingAs($user)->post(route('applications.wizard.step', $application), [
            'step' => WizardStep::ApplicationDetails->value,
            'payload' => [
                'program_head' => 'Dr. Head',
                'training_duration_years' => 4,
                'approved_resident_slots' => 5,
            ],
        ]);

        $this->assertGreaterThan(0, $application->refresh()->completion_percentage);
    }
}
