<?php

namespace Tests\Feature\Accreditation;

use App\Enums\ApplicationStatus;
use App\Enums\AuditAction;
use App\Enums\DocumentDecision;
use App\Exceptions\CompletenessReviewException;
use App\Models\AccreditationApplication;
use App\Models\CompletenessReview;
use App\Models\User;
use App\Services\Accreditation\ApplicationCompletenessService;
use App\Services\Accreditation\CompletenessReviewService;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCompletenessData;
use Tests\TestCase;

/**
 * End-to-end completeness-review workflow: transitions, blocking-item guard,
 * mandatory reviewer comment, resubmission, authorization, and audit logging.
 *
 * Synthetic data only.
 */
class CompletenessReviewTest extends TestCase
{
    use CreatesCompletenessData;
    use RefreshDatabase;

    public function test_submitted_application_enters_under_review(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];
        $reviewer = $actors['reviewer'];

        $service = app(CompletenessReviewService::class);

        $returned = $service->beginReview($reviewer, $application);

        $this->assertTrue($returned->status->isUnderCompletenessReview());
        $this->assertNotNull($returned->under_review_at);
        $this->assertSame($reviewer->id, $returned->under_review_by);

        $this->assertDatabaseHas('completeness_reviews', [
            'application_id' => $application->id,
            'decision' => CompletenessReview::UNDER_REVIEW,
        ]);
        $this->assertAudit(AuditAction::CompletenessReviewStarted, $application);
    }

    public function test_begin_review_rejects_non_submitted_application(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];
        $reviewer = $actors['reviewer'];

        // Force back to draft to violate the transition precondition.
        $application->forceFill(['status' => ApplicationStatus::Draft])->save();

        $this->expectException(CompletenessReviewException::class);
        app(CompletenessReviewService::class)->beginReview($reviewer, $application->refresh());
    }

    public function test_mark_complete_blocked_while_deficiencies_remain(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];
        $reviewer = $actors['reviewer'];

        $this->addDocumentRequirement($application);

        $service = app(CompletenessReviewService::class);
        $service->beginReview($reviewer, $application);

        // A required supporting document was never uploaded -> blocking item.
        $this->assertFalse(
            app(ApplicationCompletenessService::class)->isComplete($application->refresh()),
        );

        $this->expectException(CompletenessReviewException::class);
        $service->markComplete($reviewer, $application->refresh());
    }

    public function test_return_for_revision_requires_comment(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];
        $reviewer = $actors['reviewer'];

        $service = app(CompletenessReviewService::class);
        $service->beginReview($reviewer, $application);

        $this->expectException(CompletenessReviewException::class);
        $service->returnForRevision($reviewer, $application->refresh(), '   ');
    }

    public function test_full_cycle_return_then_resubmit_then_complete(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];
        $applicant = $actors['applicant'];
        $reviewer = $actors['reviewer'];

        $service = app(CompletenessReviewService::class);

        $service->beginReview($reviewer, $application);
        $returned = $service->returnForRevision(
            $reviewer,
            $application->refresh(),
            '[SYNTHETIC] Please upload the missing quality manual.',
        );

        $this->assertTrue($returned->status === ApplicationStatus::ReturnedForRevision);
        $this->assertSame(
            '[SYNTHETIC] Please upload the missing quality manual.',
            $returned->returned_comment,
        );
        $this->assertAudit(AuditAction::CompletenessReviewReturned, $returned);
        $this->assertDatabaseHas('completeness_reviews', [
            'application_id' => $application->id,
            'decision' => CompletenessReview::RETURNED_FOR_REVISION,
            'reviewer_comment' => '[SYNTHETIC] Please upload the missing quality manual.',
        ]);

        // Applicant resubmits.
        $resubmitted = $service->resubmit($applicant, $returned->refresh());
        $this->assertTrue($resubmitted->status === ApplicationStatus::Submitted);
        $this->assertNull($resubmitted->returned_comment);
        $this->assertAudit(AuditAction::ApplicationResubmitted, $resubmitted);

        // Upload an accepted document so no blocking items remain, then complete.
        $this->uploadAcceptedDocument($applicant, $resubmitted, $reviewer);
        $service->beginReview($reviewer, $resubmitted->refresh());
        $complete = $service->markComplete($reviewer, $resubmitted->refresh());

        $this->assertTrue($complete->status === ApplicationStatus::CompleteForInspection);
        $this->assertNotNull($complete->completed_for_inspection_at);
        $this->assertAudit(AuditAction::CompletenessReviewComplete, $complete);
    }

    public function test_returned_application_is_editable_by_applicant(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];
        $applicant = $actors['applicant'];
        $reviewer = $actors['reviewer'];

        $service = app(CompletenessReviewService::class);
        $service->beginReview($reviewer, $application);
        $returned = $service->returnForRevision(
            $reviewer,
            $application->refresh(),
            '[SYNTHETIC] Revise and resubmit.',
        );

        $this->assertTrue($returned->refresh()->status->isEditableByInstitution());
        $this->assertTrue(
            $applicant->can('resubmitCompletenessReview', $returned->refresh()),
        );
    }

    public function test_applicant_cannot_drive_reviewer_actions(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];
        $applicant = $actors['applicant'];

        $service = app(CompletenessReviewService::class);

        // Applicant cannot begin review.
        $this->expectException(CompletenessReviewException::class);
        $service->beginReview($applicant, $application);
    }

    public function test_cross_institution_reviewer_can_review_other_institutions(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];
        $reviewer = $this->crossInstitutionReviewer();

        $this->assertTrue($reviewer->canReviewCompleteness());

        $returned = app(CompletenessReviewService::class)
            ->beginReview($reviewer, $application);
        $this->assertTrue($returned->status->isUnderCompletenessReview());
    }

    public function test_completeness_summary_lists_document_deficiencies(): void
    {
        $actors = $this->submittedApplication();
        $application = $actors['application'];

        $this->addDocumentRequirement($application);

        $summary = app(ApplicationCompletenessService::class)->summary($application);

        $this->assertTrue($summary['blocking_item_count'] > 0);
        $this->assertFalse($summary['is_complete']);

        $documentDeficiencies = array_filter(
            $summary['deficiencies'],
            fn (array $item): bool => $item['category'] === 'document',
        );
        $this->assertNotEmpty($documentDeficiencies);
    }

    /**
     * Upload a single accepted supporting document for a requires_document
     * requirement so the application can pass completeness.
     */
    private function uploadAcceptedDocument(
        User $applicant,
        AccreditationApplication $application,
        User $reviewer,
    ): void {
        Storage::fake('accreditation');

        $applicationRequirement = $this->addDocumentRequirement($application);

        $file = UploadedFile::fake()->create('manual.pdf', 40, 'application/pdf');

        $document = app(DocumentService::class)->upload(
            $applicant,
            $application,
            $file,
            $applicationRequirement,
        );

        app(DocumentService::class)->review(
            $reviewer,
            $document,
            DocumentDecision::Accepted,
            '[SYNTHETIC] Accepted.',
        );
    }

    private function assertAudit(AuditAction $action, AccreditationApplication $application): void
    {
        $this->assertDatabaseHas('audit_logs', [
            'action' => $action->value,
            'auditable_type' => $application->getMorphClass(),
            'auditable_id' => $application->id,
        ]);
    }
}
