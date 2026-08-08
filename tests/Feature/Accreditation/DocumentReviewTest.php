<?php

namespace Tests\Feature\Accreditation;

use App\Enums\AuditAction;
use App\Enums\DocumentDecision;
use App\Enums\DocumentStatus;
use App\Exceptions\DocumentException;
use App\Http\Controllers\Accreditation\DocumentReviewController;
use App\Models\AccreditationApplication;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDocuments;
use Tests\Concerns\CreatesFoundationData;
use Tests\TestCase;

/**
 * Reviewer decisions, replacement workflow, and audit logging.
 *
 * @see DocumentService
 * @see DocumentReviewController
 */
class DocumentReviewTest extends TestCase
{
    use CreatesDocuments;
    use CreatesFoundationData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('accreditation');
    }

    private function uploadFor(User $applicant, AccreditationApplication $application): Document
    {
        $requirement = $application->requirements()->whereHas('requirement', fn ($q) => $q->where('code', 'DOC-REQ'))->firstOrFail();
        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => UploadedFile::fake()->create('policy.pdf', 100, 'application/pdf'),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        return Document::first();
    }

    /* 10. Replacement creates version 2 without overwriting version 1. */
    public function test_replacement_creates_version_two_without_overwriting(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $document = $this->uploadFor($applicant, $application);

        $service = app(DocumentService::class);
        $service->review($this->documentReviewer(), $document, DocumentDecision::Rejected, 'Needs work.');

        $document->refresh();
        $service->replace($applicant, $document, UploadedFile::fake()->create('policy-v2.pdf', 120, 'application/pdf'));

        $document->refresh();
        $this->assertSame(2, $document->versions()->count());
        $this->assertSame(2, $document->currentVersion->version_number);

        $v1 = $document->versions()->where('version_number', 1)->first();
        $v2 = $document->versions()->where('version_number', 2)->first();
        $this->assertNotNull($v1->superseded_at);
        $this->assertNull($v2->superseded_at);
        $this->assertNotEquals($v1->generated_filename, $v2->generated_filename);
    }

    /* 11. Accepted versions cannot be edited (immutable). */
    public function test_accepted_version_cannot_be_edited(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $document = $this->uploadFor($applicant, $application);

        $service = app(DocumentService::class);
        $service->review($this->documentReviewer(), $document, DocumentDecision::Accepted);

        $document->refresh();
        $this->assertTrue($document->isAccepted());

        // A further decision is blocked.
        $this->expectException(DocumentException::class);
        $service->review($this->documentReviewer(), $document, DocumentDecision::Rejected, 'No.');
    }

    /* 13. Reviewer can accept a document. */
    public function test_reviewer_can_accept_document(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $document = $this->uploadFor($applicant, $application);

        $reviewer = $this->documentReviewer();
        $this->actingAs($reviewer)
            ->post("/reviewer/documents/{$document->id}/review", ['decision' => 'accepted'])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Accepted->value, $document->status->value);
    }

    /* 14. Reviewer cannot accept without required authorization. */
    public function test_non_reviewer_cannot_review(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $document = $this->uploadFor($applicant, $application);

        // An institution administrator is not a reviewer.
        $this->actingAs($applicant)
            ->post("/reviewer/documents/{$document->id}/review", ['decision' => 'accepted'])
            ->assertForbidden();

        $document->refresh();
        $this->assertSame(DocumentStatus::Uploaded->value, $document->status->value);
    }

    /* 15. Reviewer rejection requires a comment. */
    public function test_rejection_requires_comment(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $document = $this->uploadFor($applicant, $application);

        $reviewer = $this->documentReviewer();
        $this->actingAs($reviewer)
            ->post("/reviewer/documents/{$document->id}/review", ['decision' => 'rejected', 'comment' => ''])
            ->assertSessionHasErrors('comment');

        $document->refresh();
        $this->assertSame(DocumentStatus::Uploaded->value, $document->status->value);
    }

    /* 15b. Rejection with a comment succeeds. */
    public function test_rejection_with_comment_succeeds(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $document = $this->uploadFor($applicant, $application);

        $reviewer = $this->documentReviewer();
        $this->actingAs($reviewer)
            ->post("/reviewer/documents/{$document->id}/review", [
                'decision' => 'rejected',
                'comment' => 'Illegible scan.',
            ])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame(DocumentStatus::Rejected->value, $document->status->value);
    }

    /* 16. Replacement request requires a comment. */
    public function test_replacement_request_requires_comment(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $document = $this->uploadFor($applicant, $application);

        $reviewer = $this->documentReviewer();
        $this->actingAs($reviewer)
            ->post("/reviewer/documents/{$document->id}/review", [
                'decision' => 'replacement_requested',
                'comment' => '',
            ])
            ->assertSessionHasErrors('comment');

        $document->refresh();
        $this->assertSame(DocumentStatus::Uploaded->value, $document->status->value);
    }

    /* 22. Review action creates an audit-log entry. */
    public function test_review_action_creates_audit_entry(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $document = $this->uploadFor($applicant, $application);

        $reviewer = $this->documentReviewer();
        $this->actingAs($reviewer)
            ->post("/reviewer/documents/{$document->id}/review", ['decision' => 'accepted'])
            ->assertRedirect();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $reviewer->id,
            'action' => AuditAction::DocumentAccepted->value,
            'auditable_type' => Document::class,
            'auditable_id' => $document->id,
        ]);
    }
}
