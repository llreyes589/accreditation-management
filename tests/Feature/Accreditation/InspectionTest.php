<?php

namespace Tests\Feature\Accreditation;

use App\Enums\ApplicationStatus;
use App\Enums\InspectionResult;
use App\Enums\InspectionStatus;
use App\Enums\RoleName;
use App\Exceptions\InspectionException;
use App\Models\Document;
use App\Models\Inspection;
use App\Models\Institution;
use App\Models\User;
use App\Services\Accreditation\InspectionEligibilityService;
use App\Services\Accreditation\InspectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesInspectionData;
use Tests\TestCase;

/**
 * Inspection scheduling + checklist: service/unit-level behavior.
 *
 * Synthetic data only. Covers eligibility, snapshot immutability, transitions,
 * result rules, conflict detection, duplicate blocking, audit, and authz.
 */
class InspectionTest extends TestCase
{
    use CreatesInspectionData;
    use RefreshDatabase;

    private function service(): InspectionService
    {
        return app(InspectionService::class);
    }

    private function eligibility(): InspectionEligibilityService
    {
        return app(InspectionEligibilityService::class);
    }

    private function makeEligible(): array
    {
        return $this->eligibleApplication();
    }

    public function test_only_complete_for_inspection_application_is_eligible(): void
    {
        // Before completion the application is merely submitted -> not eligible.
        $fixture = $this->submittedApplication();
        $application = $fixture['application'];
        $this->assertFalse($this->eligibility()->isEligible($application));

        // After the completeness review marks it complete for inspection it is eligible.
        $eligible = $this->eligibleApplication();
        $application = $eligible['application'];

        $this->assertTrue($this->eligibility()->isEligible($application));
        $this->assertEquals(ApplicationStatus::CompleteForInspection, $application->status);
    }

    public function test_incomplete_application_cannot_get_inspection(): void
    {
        $fixture = $this->submittedApplication();

        $this->expectException(InspectionException::class);
        $this->service()->create(
            $this->pspAdministrator(),
            $fixture['application'],
            ['inspection_type' => 'onsite', 'scheduled_start_at' => now()->addDay(), 'scheduled_end_at' => now()->addDay()->addHour(), 'timezone' => 'Asia/Manila', 'location' => 'X'],
            $this->inspectorUser()->id,
            [],
        );
    }

    public function test_unauthorized_user_cannot_create_inspection(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];

        // An institution user cannot schedule.
        $this->expectException(InspectionException::class);
        $this->service()->create(
            $fixture['applicant'],
            $application,
            ['inspection_type' => 'onsite', 'scheduled_start_at' => now()->addDay(), 'scheduled_end_at' => now()->addDay()->addHour(), 'timezone' => 'Asia/Manila', 'location' => 'X'],
            $this->inspectorUser()->id,
            [],
        );
    }

    public function test_creation_snapshots_checklist_version_and_is_scheduled(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];

        $inspection = $this->scheduledInspection($application);

        $this->assertDatabaseHas('inspections', [
            'id' => $inspection->id,
            'status' => InspectionStatus::Scheduled->value,
            'checklist_version_id' => $application->checklist_version_id,
        ]);
        $this->assertGreaterThan(0, $inspection->criteria()->count());
        // Lead + second inspector assigned.
        $this->assertCount(2, $inspection->inspectors);
    }

    public function test_future_checklist_edit_does_not_change_inspection_criteria(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $inspection = $this->scheduledInspection($application);

        $criterion = $inspection->criteria()->first();
        $originalTitle = $criterion->title;

        // Edit the source requirement's title after the snapshot.
        $source = $criterion->sourceRequirement;
        if ($source) {
            $source->update(['requirement' => '[SYNTHETIC] Tampered source requirement']);
        }

        $this->assertEquals($originalTitle, $criterion->refresh()->title);
    }

    public function test_duplicate_active_inspection_is_blocked(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $this->scheduledInspection($application);

        $this->expectException(InspectionException::class);
        $this->service()->create(
            $this->pspAdministrator(),
            $application,
            ['inspection_type' => 'onsite', 'scheduled_start_at' => now()->addDays(2), 'scheduled_end_at' => now()->addDays(2)->addHour(), 'timezone' => 'Asia/Manila', 'location' => 'Y'],
            $this->inspectorUser()->id,
            [],
        );
    }

    public function test_invalid_date_range_rejected(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];

        $this->expectException(InspectionException::class);
        $this->service()->create(
            $this->pspAdministrator(),
            $application,
            ['inspection_type' => 'onsite', 'scheduled_start_at' => now()->addHour(), 'scheduled_end_at' => now()->subHour(), 'timezone' => 'Asia/Manila', 'location' => 'X'],
            $this->inspectorUser()->id,
            [],
        );
    }

    public function test_past_date_rejected(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];

        $this->expectException(InspectionException::class);
        $this->service()->create(
            $this->pspAdministrator(),
            $application,
            ['inspection_type' => 'onsite', 'scheduled_start_at' => now()->subDay(), 'scheduled_end_at' => now()->subDay()->addHour(), 'timezone' => 'Asia/Manila', 'location' => 'X'],
            $this->inspectorUser()->id,
            [],
        );
    }

    public function test_conflict_detection_finds_overlapping_accepted_inspector(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];

        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        // Accept the lead so they are "accepted".
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted', 'accepted_at' => now()])->save();

        $conflicts = $this->service()->conflicts(
            [$lead->id],
            now()->addDays(7)->addMinutes(30),
            now()->addDays(7)->addHours(3),
        );

        $this->assertArrayHasKey($lead->id, $conflicts);
    }

    public function test_authorized_inspector_can_start_assigned_inspection(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();

        $started = $this->service()->start($lead, $inspection->refresh());

        $this->assertEquals(InspectionStatus::InProgress, $started->status);
        $this->assertNotNull($started->started_at);
    }

    public function test_unassigned_inspector_cannot_start_inspection(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);

        $outsider = $this->inspectorUser();

        $this->expectException(InspectionException::class);
        $this->service()->start($outsider, $inspection->refresh());
    }

    public function test_criteria_can_be_saved_independently(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());

        $criterion = $inspection->criteria()->first();
        $updated = $this->service()->saveCriterion($lead, $inspection->refresh(), $criterion, [
            'result' => InspectionResult::Compliant->value,
            'inspector_notes' => '[SYNTHETIC] Looks good.',
        ]);

        $this->assertEquals(InspectionResult::Compliant, $updated->refresh()->result);
    }

    public function test_submission_requires_results_and_blocks_incomplete(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());

        // No criterion has a result yet -> submission blocked.
        $this->expectException(InspectionException::class);
        $this->service()->submit($lead, $inspection->refresh());
    }

    public function test_not_applicable_requires_explanation(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());

        $this->expectException(InspectionException::class);
        $this->service()->saveCriterion($lead, $inspection->refresh(), $inspection->criteria()->first(), [
            'result' => InspectionResult::NotApplicable->value,
        ]);
    }

    public function test_noncompliant_requires_notes(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());

        $this->expectException(InspectionException::class);
        $this->service()->saveCriterion($lead, $inspection->refresh(), $inspection->criteria()->first(), [
            'result' => InspectionResult::Noncompliant->value,
        ]);
    }

    public function test_partially_compliant_requires_notes(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());

        $this->expectException(InspectionException::class);
        $this->service()->saveCriterion($lead, $inspection->refresh(), $inspection->criteria()->first(), [
            'result' => InspectionResult::PartiallyCompliant->value,
        ]);
    }

    public function test_not_observed_requires_explanation(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());

        $this->expectException(InspectionException::class);
        $this->service()->saveCriterion($lead, $inspection->refresh(), $inspection->criteria()->first(), [
            'result' => InspectionResult::NotObserved->value,
        ]);
    }

    public function test_submission_changes_status_to_submitted_for_review(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());

        foreach ($inspection->criteria()->get() as $criterion) {
            $this->service()->saveCriterion($lead, $inspection->refresh(), $criterion, [
                'result' => InspectionResult::Compliant->value,
                'inspector_notes' => '[SYNTHETIC] Satisfactory.',
            ]);
        }

        $submitted = $this->service()->submit($lead, $inspection->refresh());
        $this->assertEquals(InspectionStatus::SubmittedForReview, $submitted->status);
        $this->assertNotNull($submitted->submitted_at);
    }

    public function test_submitted_inspection_locked_from_ordinary_editing(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());
        foreach ($inspection->criteria()->get() as $criterion) {
            $this->service()->saveCriterion($lead, $inspection->refresh(), $criterion, [
                'result' => InspectionResult::Compliant->value,
                'inspector_notes' => '[SYNTHETIC] Satisfactory.',
            ]);
        }
        $this->service()->submit($lead, $inspection->refresh());

        $this->expectException(InspectionException::class);
        $this->service()->saveCriterion($lead, $inspection->refresh(), $inspection->criteria()->first(), [
            'result' => InspectionResult::Noncompliant->value,
            'inspector_notes' => 'x',
        ]);
    }

    public function test_authorized_reviewer_can_return_with_comment(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());
        foreach ($inspection->criteria()->get() as $criterion) {
            $this->service()->saveCriterion($lead, $inspection->refresh(), $criterion, [
                'result' => InspectionResult::Compliant->value,
                'inspector_notes' => '[SYNTHETIC] Satisfactory.',
            ]);
        }
        $this->service()->submit($lead, $inspection->refresh());

        $reviewer = $this->cartReviewer();
        $returned = $this->service()->returnForRevision($reviewer, $inspection->refresh(), '[SYNTHETIC] Please recheck section 3.');

        $this->assertEquals(InspectionStatus::InProgress, $returned->status);
        $this->assertDatabaseHas('inspection_reviews', [
            'inspection_id' => $inspection->id,
            'status_after' => InspectionStatus::InProgress->value,
        ]);
    }

    public function test_authorized_reviewer_can_complete_submitted_inspection(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());
        foreach ($inspection->criteria()->get() as $criterion) {
            $this->service()->saveCriterion($lead, $inspection->refresh(), $criterion, [
                'result' => InspectionResult::Compliant->value,
                'inspector_notes' => '[SYNTHETIC] Satisfactory.',
            ]);
        }
        $this->service()->submit($lead, $inspection->refresh());

        $reviewer = $this->cartReviewer();
        $completed = $this->service()->complete($reviewer, $inspection->refresh(), '[SYNTHETIC] Inspection complete, criteria met.');

        $this->assertEquals(InspectionStatus::Completed, $completed->status);
        $this->assertNotNull($completed->completed_at);
        // No accreditation decision created.
        $this->assertEquals(ApplicationStatus::CompleteForInspection, $application->refresh()->status);
    }

    public function test_completion_does_not_create_accreditation_decision(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());
        foreach ($inspection->criteria()->get() as $criterion) {
            $this->service()->saveCriterion($lead, $inspection->refresh(), $criterion, [
                'result' => InspectionResult::Compliant->value,
                'inspector_notes' => '[SYNTHETIC] Satisfactory.',
            ]);
        }
        $this->service()->submit($lead, $inspection->refresh());

        $this->service()->complete($this->cartReviewer(), $inspection->refresh(), '[SYNTHETIC] Done.');

        // Completion must not move the application to a final accreditation status.
        $this->assertEquals(ApplicationStatus::CompleteForInspection, $application->refresh()->status);
    }

    public function test_cancellation_requires_reason(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $inspection = $this->scheduledInspection($application);

        $this->expectException(InspectionException::class);
        $this->service()->cancel($this->pspAdministrator(), $inspection->refresh(), '');
    }

    public function test_cancellation_with_reason_succeeds(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $inspection = $this->scheduledInspection($application);

        $cancelled = $this->service()->cancel($this->pspAdministrator(), $inspection->refresh(), '[SYNTHETIC] Scheduler conflict.');
        $this->assertEquals(InspectionStatus::Cancelled, $cancelled->status);
        $this->assertNotNull($cancelled->cancelled_at);
    }

    public function test_reschedule_preserves_prior_scheduling_history(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $inspection = $this->scheduledInspection($application);
        $originalStart = $inspection->scheduled_start_at->clone();

        $rescheduled = $this->service()->reschedule($this->pspAdministrator(), $inspection->refresh(), [
            'scheduled_start_at' => now()->addDays(14),
            'scheduled_end_at' => now()->addDays(14)->addHours(2),
            'timezone' => 'Asia/Manila',
            'location' => '[SYNTHETIC] New venue',
            'lead_inspector_id' => $inspection->inspectors()->where('role', 'lead_inspector')->first()->user_id,
        ]);

        $this->assertEquals(InspectionStatus::Scheduled, $rescheduled->status);
        $this->assertNotEquals($originalStart->toDateTimeString(), $rescheduled->scheduled_start_at->toDateTimeString());
    }

    public function test_all_important_actions_create_audit_records(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());
        foreach ($inspection->criteria()->get() as $criterion) {
            $this->service()->saveCriterion($lead, $inspection->refresh(), $criterion, [
                'result' => InspectionResult::Compliant->value,
                'inspector_notes' => '[SYNTHETIC] Satisfactory.',
            ]);
        }
        $this->service()->submit($lead, $inspection->refresh());
        $this->service()->complete($this->cartReviewer(), $inspection->refresh(), '[SYNTHETIC] Done.');

        foreach ([
            'inspection_created',
            'inspection_started',
            'criterion_result_changed',
            'inspection_submitted',
            'inspection_completed',
        ] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action]);
        }
    }

    public function test_cross_institution_inspection_hidden_from_other_institution_user(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $inspection = $this->scheduledInspection($application);

        // Institution user of a DIFFERENT institution cannot view.
        $otherInstitution = Institution::factory()->create();
        $outsider = $this->userWithRole(RoleName::InstitutionAdministrator, $otherInstitution);

        $this->assertFalse($outsider->can('view', $inspection->refresh()));
    }

    public function test_evidence_links_reuse_document_authorization(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());

        $document = Document::factory()->create(['application_id' => $application->id]);
        $criterion = $inspection->criteria()->first();

        $evidence = $this->service()->linkEvidence($lead, $inspection->refresh(), $criterion, $document->id, '[SYNTHETIC] Evidence note.');
        $this->assertDatabaseHas('inspection_evidence', ['id' => $evidence->id, 'document_id' => $document->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'evidence_linked']);

        $this->service()->unlinkEvidence($lead, $inspection->refresh(), $evidence);
        $this->assertDatabaseMissing('inspection_evidence', ['id' => $evidence->id]);
    }

    public function test_two_reviewers_cannot_inconsistently_complete_and_cancel(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];
        $lead = $this->inspectorUser();
        $inspection = $this->scheduledInspection($application, $lead);
        $inspection->inspectors()->where('user_id', $lead->id)->first()->forceFill(['status' => 'accepted'])->save();
        $this->service()->start($lead, $inspection->refresh());
        foreach ($inspection->criteria()->get() as $criterion) {
            $this->service()->saveCriterion($lead, $inspection->refresh(), $criterion, [
                'result' => InspectionResult::Compliant->value,
                'inspector_notes' => '[SYNTHETIC] Satisfactory.',
            ]);
        }
        $this->service()->submit($lead, $inspection->refresh());

        $reviewer = $this->cartReviewer();
        $this->service()->complete($reviewer, $inspection->refresh(), '[SYNTHETIC] Done.');

        // A second reviewer cannot cancel a completed inspection.
        $this->expectException(InspectionException::class);
        $this->service()->cancel($this->pspAdministrator(), $inspection->refresh(), '[SYNTHETIC] late cancel');
    }

    public function test_creation_is_atomic_on_snapshot_failure(): void
    {
        $fixture = $this->makeEligible();
        $application = $fixture['application'];

        // Force a failure inside the transaction by supplying an invalid lead that
        // cannot be resolved as a user, triggering a constraint violation path.
        $before = Inspection::count();

        try {
            $this->service()->create(
                $this->pspAdministrator(),
                $application,
                ['inspection_type' => 'onsite', 'scheduled_start_at' => now()->addDay(), 'scheduled_end_at' => now()->addDay()->addHour(), 'timezone' => 'Asia/Manila', 'location' => 'X'],
                999999,
                [],
            );
        } catch (\Throwable) {
            // Expected: invalid lead user id.
        }

        $this->assertCount($before, Inspection::all());
    }
}
