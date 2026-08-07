<?php

namespace Tests\Feature\Accreditation;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\ProgramStatus;
use App\Enums\RoleName;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Services\Accreditation\ChecklistResponseSynchronizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesFoundationData;
use Tests\Concerns\CreatesWizardData;
use Tests\TestCase;

/**
 * Requirements 8, 9, 10, 13, 15: submission.
 */
class SubmissionTest extends TestCase
{
    use CreatesFoundationData, CreatesWizardData, RefreshDatabase;

    public function test_submission_changes_status_from_draft_to_submitted(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = $this->submittableDraft($user, $program);

        $this->assertSame(ApplicationStatus::Draft, $application->status);

        $this->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertRedirect(route('applications.confirmation', $application));

        $application->refresh();

        $this->assertSame(ApplicationStatus::Submitted, $application->status);
        $this->assertNotNull($application->submitted_at);
        $this->assertSame($user->id, $application->submitted_by);
        $this->assertSame(100, $application->completion_percentage);
    }

    public function test_incomplete_required_data_prevents_submission(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        // No section payloads saved at all.
        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertSessionHasErrors('steps');

        $this->assertSame(ApplicationStatus::Draft, $application->refresh()->status);
        $this->assertNull($application->submitted_at);
    }

    public function test_incomplete_mandatory_checklist_prevents_submission(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->withCompletedSections()
            ->create(['created_by' => $user->id]);

        app(ChecklistResponseSynchronizer::class)->sync($application);

        $application->forceFill([
            'declaration_accepted_at' => now(),
            'declaration_accepted_by' => $user->id,
        ])->save();

        // Mandatory requirement left unanswered.
        $this->actingAs($user)
            ->post(route('applications.submit', $application->refresh()))
            ->assertSessionHasErrors('checklist');

        $this->assertSame(ApplicationStatus::Draft, $application->refresh()->status);
    }

    public function test_declaration_is_required_before_submission(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = $this->submittableDraft($user, $program);

        // Retract the declaration.
        $application->forceFill([
            'declaration_accepted_at' => null,
            'declaration_accepted_by' => null,
        ])->save();

        $this->actingAs($user)
            ->post(route('applications.submit', $application->refresh()))
            ->assertSessionHasErrors('declaration');

        $this->assertSame(ApplicationStatus::Draft, $application->refresh()->status);
    }

    public function test_declaration_endpoint_rejects_an_unchecked_box(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.wizard.declaration', $application), [
                'declaration_accepted' => false,
            ])
            ->assertSessionHasErrors('declaration_accepted');

        $this->assertNull($application->refresh()->declaration_accepted_at);
    }

    public function test_an_inactive_program_prevents_submission(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = $this->submittableDraft($user, $program);

        $program->update(['status' => ProgramStatus::Lapsed]);

        $this->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertSessionHasErrors('program_id');

        $this->assertSame(ApplicationStatus::Draft, $application->refresh()->status);
    }

    public function test_submitted_applications_cannot_be_edited_by_applicants(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = $this->submittableDraft($user, $program);
        $originalDirector = $application->leadership_staffing['program_director'];
        $this->actingAs($user)->post(route('applications.submit', $application));
        $application->refresh();

        // Step saves, checklist writes, and re-submission are all refused.
        $this->actingAs($user)
            ->post(route('applications.wizard.step', $application), [
                'step' => WizardStep::LeadershipStaffing->value,
                'payload' => ['program_director' => 'Changed', 'consultant_count' => 1],
            ])
            ->assertForbidden();

        $row = $application->requirements()->firstOrFail();

        $this->actingAs($user)
            ->post(route('applications.wizard.checklist', $application), [
                'requirement_id' => $row->id,
                'applicant_status' => ApplicantResponseStatus::InProgress->value,
            ])
            ->assertForbidden();

        $this->actingAs($user)
            ->post(route('applications.submit', $application))
            ->assertForbidden();

        // And the stored data is unchanged.
        $this->assertSame(
            $originalDirector,
            $application->refresh()->leadership_staffing['program_director'],
        );
    }

    public function test_submission_creates_an_audit_log_entry(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = $this->submittableDraft($user, $program);

        $this->actingAs($user)->post(route('applications.submit', $application));

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApplicationSubmitted->value,
            'actor_id' => $user->id,
            'institution_id' => $institution->id,
            'auditable_type' => AccreditationApplication::class,
            'auditable_id' => $application->id,
        ]);

        $log = AuditLog::where('action', AuditAction::ApplicationSubmitted->value)->firstOrFail();

        $this->assertSame(ApplicationStatus::Draft->value, $log->context['from_status']);
        $this->assertSame(ApplicationStatus::Submitted->value, $log->context['to_status']);

        // No credential material is ever recorded.
        $encoded = json_encode($log->context);
        $this->assertStringNotContainsString('password', strtolower((string) $encoded));
        $this->assertStringNotContainsString('token', strtolower((string) $encoded));
    }

    public function test_creating_and_saving_steps_are_audit_logged(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);
        $this->synthChecklist();

        $this->actingAs($user)->post(route('applications.store'), [
            'program_id' => $program->id,
            'application_type' => 'new',
        ]);

        $application = AccreditationApplication::firstOrFail();

        $this->actingAs($user)->post(route('applications.wizard.step', $application), [
            'step' => WizardStep::ApplicationDetails->value,
            'payload' => [
                'program_head' => 'Dr. Head',
                'training_duration_years' => 4,
                'approved_resident_slots' => 5,
            ],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApplicationCreated->value,
            'auditable_id' => $application->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApplicationStepSaved->value,
            'auditable_id' => $application->id,
        ]);
    }

    public function test_the_submit_operation_is_transactional(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = $this->submittableDraft($user, $program);

        // Force the audit-log write (the last step inside the transaction) to
        // explode, then assert the status change was rolled back with it.
        DB::listen(function ($query) {
            if (str_contains(strtolower($query->sql), 'insert into "audit_logs"')
                || str_contains(strtolower($query->sql), 'insert into `audit_logs`')) {
                throw new \RuntimeException('Simulated audit-log failure.');
            }
        });

        try {
            $this->actingAs($user)->post(route('applications.submit', $application));
        } catch (\Throwable) {
            // Expected.
        }

        $application->refresh();

        $this->assertSame(
            ApplicationStatus::Draft,
            $application->status,
            'Status must roll back when the transaction fails.',
        );
        $this->assertNull($application->submitted_at);
        $this->assertNull($application->submitted_by);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_draft_can_be_withdrawn_and_is_audit_logged(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::InstitutionAdministrator, $institution);
        $this->synthChecklist();

        $application = AccreditationApplication::factory()
            ->forProgram($program)
            ->create(['created_by' => $user->id]);

        $this->actingAs($user)
            ->post(route('applications.withdraw', $application))
            ->assertRedirect(route('applications.index'));

        $application->refresh();

        $this->assertSame(ApplicationStatus::Withdrawn, $application->status);
        $this->assertNotNull($application->withdrawn_at);
        $this->assertSame($user->id, $application->withdrawn_by);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::ApplicationWithdrawn->value,
            'auditable_id' => $application->id,
        ]);
    }

    public function test_a_submitted_application_cannot_be_withdrawn(): void
    {
        $institution = Institution::factory()->create();
        $program = $this->activeProgram($institution);
        $user = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $application = $this->submittableDraft($user, $program);
        $this->actingAs($user)->post(route('applications.submit', $application));

        $this->actingAs($user)
            ->post(route('applications.withdraw', $application->refresh()))
            ->assertForbidden();

        $this->assertSame(ApplicationStatus::Submitted, $application->refresh()->status);
    }
}
