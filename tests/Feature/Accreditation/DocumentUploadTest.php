<?php

namespace Tests\Feature\Accreditation;

use App\Enums\AuditAction;
use App\Enums\DocumentDecision;
use App\Enums\DocumentStatus;
use App\Http\Controllers\Accreditation\ApplicationDocumentController;
use App\Models\Document;
use App\Models\User;
use App\Services\Documents\DocumentCompletenessService;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesDocuments;
use Tests\Concerns\CreatesFoundationData;
use Tests\TestCase;

/**
 * Applicant upload behaviour, validation, versioning, and authorization.
 *
 * @see DocumentService
 * @see ApplicationDocumentController
 */
class DocumentUploadTest extends TestCase
{
    use CreatesDocuments;
    use CreatesFoundationData;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('accreditation');
    }

    private function pdf(string $name = 'policy.pdf', int $kilobytes = 100): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    /* 1. Authorized institution user can upload a valid PDF. */
    public function test_authorized_user_can_upload_valid_pdf(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->whereHas('requirement', fn ($q) => $q->where('code', 'DOC-REQ'))->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('documents', [
            'application_id' => $application->id,
            'status' => DocumentStatus::Uploaded->value,
        ]);
        Storage::disk('accreditation')->assertExists(Document::first()->currentVersion->storage_path);
    }

    /* 2. Unauthorized user cannot upload to another institution's application. */
    public function test_cannot_upload_to_another_institution_application(): void
    {
        $owner = $this->documentApplicant();
        $application = $this->documentApplication($owner);

        $intruder = $this->documentApplicant(); // different institution
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($intruder)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('documents', 0);
    }

    /* 3. Invalid extension is rejected. */
    public function test_invalid_extension_is_rejected(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => UploadedFile::fake()->create('script.exe', 10, 'application/octet-stream'),
                'application_requirement_id' => (string) $requirement->id,
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('documents', 0);
    }

    /* 4. Invalid MIME type is rejected. */
    public function test_invalid_mime_type_is_rejected(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        // php extension with a pdf-like size but disallowed MIME.
        $file = UploadedFile::fake()->create('malicious.php', 10, 'text/php');

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $file,
                'application_requirement_id' => (string) $requirement->id,
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('documents', 0);
    }

    /* 5. Oversized file is rejected. */
    public function test_oversized_file_is_rejected(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf('big.pdf', 30 * 1024), // 30 MB > 20 MB limit
                'application_requirement_id' => (string) $requirement->id,
            ])
            ->assertSessionHasErrors('document');

        $this->assertDatabaseCount('documents', 0);
    }

    /* 6. Executable and archive formats are rejected. */
    public function test_executable_and_archive_formats_are_rejected(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        foreach (['evil.exe', 'archive.zip', 'page.html', 'img.svg', 'script.js'] as $name) {
            $this->actingAs($applicant)
                ->post("/applications/{$application->id}/documents", [
                    'document' => UploadedFile::fake()->create($name, 5),
                    'application_requirement_id' => (string) $requirement->id,
                ])
                ->assertSessionHasErrors('document');
        }

        $this->assertDatabaseCount('documents', 0);
    }

    /* 7. Uploaded files are stored outside public storage. */
    public function test_files_are_stored_outside_public_storage(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $version = Document::first()->currentVersion;
        $this->assertStringStartsNotWith('public', $version->storage_disk);
        $this->assertStringNotContainsString('app/public', $version->storage_path);
        Storage::disk('accreditation')->assertExists($version->storage_path);
    }

    /* 8. Stored filename is not the original client filename. */
    public function test_stored_filename_is_not_original(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf('my-secret-file.pdf'),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $version = Document::first()->currentVersion;
        $this->assertNotEquals('my-secret-file.pdf', $version->generated_filename);
        $this->assertNotEquals('my-secret-file.pdf', $version->storage_path);
    }

    /* 9. New upload creates version 1. */
    public function test_new_upload_creates_version_one(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $document = Document::first();
        $this->assertSame(1, $document->versions()->count());
        $this->assertSame(1, $document->currentVersion->version_number);
    }

    /* 12. Applicant cannot change document status. */
    public function test_applicant_cannot_change_document_status(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $document = Document::first();
        $this->assertSame(DocumentStatus::Uploaded->value, $document->status->value);

        // No review route is exposed to applicants; policy forbids it.
        $this->assertFalse($applicant->can('accept', $document));
    }

    /* 17. Rejected documents do not satisfy required-document completeness. */
    public function test_rejected_document_does_not_satisfy_completeness(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->whereHas('requirement', fn ($q) => $q->where('code', 'DOC-REQ'))->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $document = Document::first();
        $reviewer = $this->documentReviewer();
        $service = app(DocumentService::class);
        $service->review($reviewer, $document, DocumentDecision::Rejected, 'Does not meet criteria.');

        $summary = app(DocumentCompletenessService::class)->summary($application);
        $this->assertFalse($summary['complete']);
        $this->assertSame(1, $summary['rejected']);
    }

    /* 18. Accepted documents satisfy required-document completeness. */
    public function test_accepted_document_satisfies_completeness(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->whereHas('requirement', fn ($q) => $q->where('code', 'DOC-REQ'))->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $document = Document::first();
        $reviewer = $this->documentReviewer();
        $service = app(DocumentService::class);
        $service->review($reviewer, $document, DocumentDecision::Accepted);

        $summary = app(DocumentCompletenessService::class)->summary($application);
        $this->assertTrue($summary['complete']);
        $this->assertSame(1, $summary['accepted']);
    }

    /* 19. Reviewer can download only authorized documents (policy). */
    public function test_reviewer_download_is_policy_checked(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $document = Document::first();
        $reviewer = $this->documentReviewer();

        $this->actingAs($reviewer)
            ->get("/applications/{$application->id}/documents/{$document->id}/download")
            ->assertOk();

        // An unaffiliated institution administrator cannot download.
        $stranger = $this->documentApplicant();
        $this->actingAs($stranger)
            ->get("/applications/{$application->id}/documents/{$document->id}/download")
            ->assertForbidden();
    }

    /* 20. URL ID tampering cannot reveal another institution's document. */
    public function test_url_tampering_cannot_reveal_other_institutions_document(): void
    {
        $applicantA = $this->documentApplicant();
        $appA = $this->documentApplication($applicantA);
        $reqA = $appA->requirements()->firstOrFail();
        $this->actingAs($applicantA)
            ->post("/applications/{$appA->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $reqA->id,
            ]);
        $docA = Document::first();

        // A second institution + its applicant.
        $applicantB = $this->documentApplicant();
        $appB = $this->documentApplication($applicantB);
        $reqB = $appB->requirements()->firstOrFail();
        $this->actingAs($applicantB)
            ->post("/applications/{$appB->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $reqB->id,
            ]);
        $docB = $appB->documents()->first();

        // Applicant B tries to access applicant A's document via ID tampering.
        $this->actingAs($applicantB)
            ->get("/applications/{$appB->id}/documents/{$docA->id}/download")
            ->assertForbidden();
    }

    /* 21. Upload creates an audit-log entry. */
    public function test_upload_creates_audit_entry(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $applicant->id,
            'action' => AuditAction::DocumentUploaded->value,
            'auditable_type' => Document::class,
            'auditable_id' => Document::first()->id,
        ]);
    }

    /* 23. Duplicate upload for a single-document requirement is rejected (no inconsistent record). */
    public function test_duplicate_upload_for_single_document_requirement_is_rejected(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->whereHas('requirement', fn ($q) => $q->where('code', 'DOC-REQ'))->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $countAfterFirst = Document::count();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf('second.pdf'),
                'application_requirement_id' => (string) $requirement->id,
            ])
            ->assertSessionHasErrors('document');

        $this->assertSame($countAfterFirst, Document::count());
    }

    /* 24. Existing applications retain their document history. */
    public function test_existing_applications_retain_document_history(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);
        $requirement = $application->requirements()->firstOrFail();

        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $requirement->id,
            ]);

        $document = Document::first();
        $reviewer = $this->documentReviewer();
        $service = app(DocumentService::class);

        // Reject then replace keeps both versions and the review history.
        $service->review($reviewer, $document, DocumentDecision::Rejected, 'Does not meet criteria.');
        $service->replace($applicant, $document, $this->pdf('v2.pdf'));
        $service->review($reviewer, $document, DocumentDecision::Accepted);

        $document->refresh();
        $this->assertSame(2, $document->versions()->count());
        $this->assertSame(2, $document->reviews()->count());
        $this->assertSame(DocumentStatus::Accepted->value, $document->status->value);
    }

    /* 25. Optional documents do not block application completeness. */
    public function test_optional_documents_do_not_block_completeness(): void
    {
        $applicant = $this->documentApplicant();
        $application = $this->documentApplication($applicant);

        // Only the mandatory DOC-REQ is satisfied; OPT-REQ (optional) is left empty.
        $mandatory = $application->requirements()->whereHas('requirement', fn ($q) => $q->where('code', 'DOC-REQ'))->firstOrFail();
        $this->actingAs($applicant)
            ->post("/applications/{$application->id}/documents", [
                'document' => $this->pdf(),
                'application_requirement_id' => (string) $mandatory->id,
            ]);
        $doc = Document::first();
        $service = app(DocumentService::class);
        $service->review($this->documentReviewer(), $doc, DocumentDecision::Accepted);

        $summary = app(DocumentCompletenessService::class)->summary($application);
        $this->assertTrue($summary['complete']);
        $this->assertSame(1, $summary['accepted']);
    }
}
