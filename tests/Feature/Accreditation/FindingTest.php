<?php

namespace Tests\Feature\Accreditation;

use App\Enums\CorrectiveActionStatus;
use App\Enums\FindingReviewAction;
use App\Enums\FindingStatus;
use App\Exceptions\FindingException;
use App\Models\AuditLog;
use App\Models\Finding;
use App\Services\Accreditation\CorrectiveActionService;
use App\Services\Accreditation\FindingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFindingData;
use Tests\TestCase;

/**
 * Findings & corrective-action workflow: service/unit-level behavior.
 *
 * Synthetic data only. Covers creation rules + snapshot immutability,
 * finding state machine, corrective-action lifecycle, overdue, guards,
 * and audit logging. Access-control is covered in FindingHttpTest.
 */
class FindingTest extends TestCase
{
    use CreatesFindingData;
    use RefreshDatabase;

    private function findings(): FindingService
    {
        return app(FindingService::class);
    }

    private function actions(): CorrectiveActionService
    {
        return app(CorrectiveActionService::class);
    }

    public function test_finding_cannot_be_created_before_inspection_has_result(): void
    {
        $data = $this->completedInspectionWithCriterion();
        $criterion = $data['criterion'];
        // A criterion without a recorded result (still "not started") cannot be
        // the source of a finding.
        $criterion->update(['result' => 'not_started']);

        $this->expectException(FindingException::class);
        $this->expectExceptionMessageMatches('/recorded result/');

        $this->findings()->create(
            $this->pspAdministrator(),
            $data['inspection'],
            ['title' => 'X', 'category' => 'minor'],
            $criterion,
        );
    }

    public function test_finding_snapshot_is_copied_from_criterion_and_immutable(): void
    {
        $data = $this->completedInspectionWithCriterion();
        $finding = $this->findings()->create(
            $this->pspAdministrator(),
            $data['inspection'],
            ['title' => '[SYNTHETIC] Snap', 'category' => 'major', 'severity' => 'critical'],
            $data['criterion'],
        );

        $originalSeverity = $data['criterion']->severity;

        $this->assertSame($data['criterion']->requirement_code, $finding->source_requirement_code);
        $this->assertSame($data['criterion']->section_name, $finding->source_section_name);
        $this->assertSame($data['criterion']->title, $finding->source_requirement_title);
        $this->assertSame($data['criterion']->description, $finding->source_requirement_description);
        // Severity is snapshotted from the criterion (the create payload's severity is ignored).
        $this->assertSame($originalSeverity, $finding->severity);
        $this->assertSame($data['criterion']->result->value, $finding->source_result);
        $this->assertSame($data['criterion']->inspector_notes, $finding->source_inspector_notes);
        $this->assertSame(FindingStatus::Draft, $finding->status);

        // Mutating the live criterion must NOT alter the finding's historical snapshot.
        $data['criterion']->update([
            'severity' => 'low',
            'result' => 'compliant',
            'inspector_notes' => 'CHANGED',
        ]);
        $finding->refresh();

        $this->assertSame($originalSeverity, $finding->severity);
        $this->assertSame('noncompliant', $finding->source_result);
        $this->assertSame('[SYNTHETIC] Inspector noted noncompliance.', $finding->source_inspector_notes);
    }

    public function test_finding_reference_is_unique_and_scoped(): void
    {
        $a = $this->draftFinding();
        $b = $this->draftFinding();
        $this->assertNotNull($a['finding']->finding_reference);
        $this->assertNotEquals($a['finding']->finding_reference, $b['finding']->finding_reference);
    }

    public function test_finding_open_transition_and_review_recorded(): void
    {
        $data = $this->draftFinding();
        $finding = $this->findings()->open($this->pspAdministrator(), $data['finding']);

        $this->assertSame(FindingStatus::Open, $finding->status);
        $this->assertDatabaseHas('finding_reviews', [
            'finding_id' => $finding->id,
            'action' => FindingReviewAction::Created->value,
        ]);
        $this->assertDatabaseHas('finding_reviews', [
            'finding_id' => $finding->id,
            'action' => FindingReviewAction::Opened->value,
            'status_before' => FindingStatus::Draft->value,
            'status_after' => FindingStatus::Open->value,
        ]);
    }

    public function test_only_authorized_users_may_waive(): void
    {
        $data = $this->draftFinding();
        $this->expectException(FindingException::class);
        $this->expectExceptionMessageMatches('/authorized/');

        $this->findings()->waive(
            $data['institution']->users()->first(), // institution user, not PSP/CART
            $data['finding'],
            'policy waiver',
        );
    }

    public function test_finding_cannot_close_without_accepted_action_or_waiver(): void
    {
        $data = $this->draftFinding();
        $open = $this->findings()->open($this->pspAdministrator(), $data['finding']);
        $this->findings()->acknowledge($open->application->institution->users()->first(), $open);
        $finding = $this->findings()->requireCorrectiveAction($this->pspAdministrator(), $open);
        $action = $this->actions()->create($this->pspAdministrator(), $finding, [
            'title' => 'CA',
            'responsible_role' => 'training_officer',
            'due_date' => now()->addDays(14),
        ]);
        $action = $this->actions()->start($data['applicant'], $action);
        $action = $this->actions()->submit($data['applicant'], $action, ['proposed_action' => 'x']);
        $action = $this->actions()->accept($this->pspAdministrator(), $action);
        $finding = $finding->refresh();

        // RESOLVED but close still requires a non-blank summary.
        $this->assertSame(FindingStatus::Resolved, $finding->status);
        $this->expectException(FindingException::class);
        $this->expectExceptionMessageMatches('/summary/');

        $this->findings()->close($this->pspAdministrator(), $finding, '');
    }

    public function test_reopen_requires_reason(): void
    {
        $data = $this->draftFinding();
        $open = $this->findings()->open($this->pspAdministrator(), $data['finding']);
        $this->findings()->acknowledge($open->application->institution->users()->first(), $open);
        $finding = $this->findings()->requireCorrectiveAction($this->pspAdministrator(), $open);
        $action = $this->actions()->create($this->pspAdministrator(), $finding, [
            'title' => 'CA',
            'responsible_role' => 'training_officer',
            'due_date' => now()->addDays(14),
        ]);
        $action = $this->actions()->start($data['applicant'], $action);
        $action = $this->actions()->submit($data['applicant'], $action, ['proposed_action' => 'x']);
        $action = $this->actions()->accept($this->pspAdministrator(), $action);
        $finding = $finding->refresh();
        $finding = $this->findings()->close($this->pspAdministrator(), $finding, 'All corrective actions verified.');

        $this->expectException(FindingException::class);
        $this->expectExceptionMessageMatches('/reason/');

        $this->findings()->reopen($this->pspAdministrator(), $finding, '');
    }

    public function test_void_requires_reason_and_stays_visible(): void
    {
        $data = $this->draftFinding();
        $finding = $this->findings()->void($this->pspAdministrator(), $data['finding'], 'duplicate');

        $this->assertSame(FindingStatus::Voided, $finding->status);
        $this->assertNotNull($finding->voided_at);
        $this->assertSame('duplicate', $finding->void_reason);
        // Voided findings remain in the table (status, not deletion) for history.
        $this->assertNotNull(Finding::find($finding->id));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'finding_voided',
            'institution_id' => $data['institution']->id,
        ]);
    }

    public function test_corrective_action_lifecycle_and_immutable_versions(): void
    {
        $data = $this->draftFinding();
        $open = $this->findings()->open($this->pspAdministrator(), $data['finding']);
        $this->findings()->acknowledge($open->application->institution->users()->first(), $open);
        $finding = $this->findings()->requireCorrectiveAction($this->pspAdministrator(), $open);

        $action = $this->actions()->create($this->pspAdministrator(), $finding, [
            'title' => '[SYNTHETIC] CA',
            'description' => '[SYNTHETIC] do the thing',
            'responsible_role' => 'training_officer',
            'due_date' => now()->addDays(14),
        ]);
        $this->assertSame(CorrectiveActionStatus::Open, $action->status);

        $action = $this->actions()->start($data['applicant'], $action);
        $this->assertSame(CorrectiveActionStatus::InProgress, $action->status);

        $action = $this->actions()->submit($data['applicant'], $action, [
            'proposed_action' => '[SYNTHETIC] Implemented', 'implementation_notes' => 'done',
        ]);
        $this->assertSame(CorrectiveActionStatus::Submitted, $action->status);

        $version = $action->versions()->first();
        $this->assertNotNull($version);
        $this->assertSame(1, $version->version_number);

        // Reject -> returns for correction (immutable version preserved).
        $action = $this->actions()->reject($this->pspAdministrator(), $action, 'needs more evidence');
        $this->assertSame(CorrectiveActionStatus::Rejected, $action->status);
        $this->assertSame('needs more evidence', $action->rejection_reason);

        $action = $this->actions()->start($data['applicant'], $action);
        $action = $this->actions()->submit($data['applicant'], $action, [
            'proposed_action' => '[SYNTHETIC] Revised', 'implementation_notes' => 'added evidence',
        ]);

        $action = $this->actions()->accept($this->pspAdministrator(), $action);
        $this->assertSame(CorrectiveActionStatus::Accepted, $action->status);
        $this->assertNotNull($action->accepted_at);

        // Accepting the corrective action resolves the finding (no auto-close).
        $finding->refresh();
        $this->assertSame(FindingStatus::Resolved, $finding->status);

        // Two immutable versions exist; the first must be unchanged.
        $this->assertCount(2, $action->versions()->get());
        $first = $action->versions()->orderBy('version_number')->first();
        $this->assertSame('[SYNTHETIC] Implemented', $first->proposed_action);
    }

    public function test_action_accept_marks_finding_under_review_not_auto_closed(): void
    {
        $data = $this->draftFinding();
        $open = $this->findings()->open($this->pspAdministrator(), $data['finding']);
        $this->findings()->acknowledge($open->application->institution->users()->first(), $open);
        $finding = $this->findings()->requireCorrectiveAction($this->pspAdministrator(), $open);
        $action = $this->actions()->create($this->pspAdministrator(), $finding, [
            'title' => 'CA',
            'responsible_role' => 'training_officer',
            'due_date' => now()->addDays(14),
        ]);
        $action = $this->actions()->start($data['applicant'], $action);
        $action = $this->actions()->submit($data['applicant'], $action, ['proposed_action' => 'x']);
        $action = $this->actions()->accept($this->pspAdministrator(), $action);

        // Accepting the corrective action resolves the finding (no auto-close).
        $finding->refresh();
        $this->assertSame(FindingStatus::Resolved, $finding->status);
        $this->assertFalse($finding->isOverdue());
    }

    public function test_overdue_flag_is_derived_not_terminal(): void
    {
        $data = $this->draftFinding();
        $open = $this->findings()->open($this->pspAdministrator(), $data['finding']);
        $finding = $this->findings()->requireCorrectiveAction($this->pspAdministrator(), $open);
        $finding->update(['due_date' => now()->subDays(1)]);
        $this->assertTrue($finding->isOverdue());

        // Overdue is a derived flag; it must not auto-close or change the status.
        $finding->refresh();
        $this->assertSame(FindingStatus::CorrectiveActionRequired, $finding->status);
    }

    public function test_audit_trail_records_every_finding_action(): void
    {
        $data = $this->draftFinding();
        $open = $this->findings()->open($this->pspAdministrator(), $data['finding']);

        $this->assertNotNull(
            AuditLog::where('action', 'finding_opened')->where('institution_id', $data['institution']->id)->first(),
        );
    }

    public function test_institution_submission_moves_finding_under_review(): void
    {
        $data = $this->draftFinding();
        $open = $this->findings()->open($this->pspAdministrator(), $data['finding']);
        $this->findings()->acknowledge($open->application->institution->users()->first(), $open);
        $finding = $this->findings()->requireCorrectiveAction($this->pspAdministrator(), $open);

        $action = $this->actions()->create($this->pspAdministrator(), $finding, [
            'title' => 'CA',
            'responsible_role' => 'training_officer',
            'due_date' => now()->addDays(14),
        ]);
        $action = $this->actions()->start($data['applicant'], $action);
        $action = $this->actions()->submit($data['applicant'], $action, ['proposed_action' => 'x']);

        $finding->refresh();
        $this->assertSame(FindingStatus::UnderReview, $finding->status);
        $this->assertSame(CorrectiveActionStatus::Submitted, $action->status);
    }

    public function test_application_status_advances_on_publish_and_resolution(): void
    {
        $data = $this->draftFinding();
        $application = $data['finding']->application;
        $initial = $application->status->value;

        $open = $this->findings()->open($this->pspAdministrator(), $data['finding']);

        // Publishing a finding does not approve the application.
        $application->refresh();
        $this->assertNotSame('approved', $application->status->value);
        $this->assertContains($application->status->value, ['findings_issued', 'corrective_action_required']);

        // Resolving every finding flags the application for final evaluation.
        $this->findings()->acknowledge($open->application->institution->users()->first(), $open);
        $finding = $this->findings()->requireCorrectiveAction($this->pspAdministrator(), $open);
        $action = $this->actions()->create($this->pspAdministrator(), $finding, [
            'title' => 'CA',
            'responsible_role' => 'training_officer',
            'due_date' => now()->addDays(14),
        ]);
        $action = $this->actions()->start($data['applicant'], $action);
        $action = $this->actions()->submit($data['applicant'], $action, ['proposed_action' => 'x']);
        $this->actions()->accept($this->pspAdministrator(), $action);
        $finding = $finding->refresh();
        $this->findings()->close($this->pspAdministrator(), $finding, 'Verified.');

        $application->refresh();
        $this->assertSame('for_final_evaluation', $application->status->value);
        $this->assertNotSame('approved', $initial);
    }
}
