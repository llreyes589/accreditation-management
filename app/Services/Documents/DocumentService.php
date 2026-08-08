<?php

namespace App\Services\Documents;

use App\Enums\AuditAction;
use App\Enums\DocumentDecision;
use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use App\Exceptions\DocumentException;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentVersion;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orchestrates the document lifecycle: applicant upload/replacement, reviewer
 * decisions, and withdrawal.
 *
 * All metadata changes run inside a database transaction. If the transaction
 * rolls back, any file already written to the private disk is removed so a
 * failed upload never leaves an orphaned file behind.
 */
class DocumentService
{
    public function __construct(
        private readonly DocumentStorageService $storage,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Upload a document for an application requirement (or at the application
     * level when no requirement is supplied).
     *
     * Creates the logical document and its first immutable version, marks the
     * document UPLOADED, and writes an audit entry.
     *
     * @throws DocumentException
     */
    public function upload(
        User $user,
        AccreditationApplication $application,
        UploadedFile $file,
        ?ApplicationRequirement $requirement = null,
        ?string $documentType = null,
    ): Document {
        if ($requirement !== null && $requirement->accreditation_application_id !== $application->id) {
            throw DocumentException::invalidFile('requirement does not belong to this application.');
        }

        // Prevent duplicate active submissions when multiples are not allowed.
        if ($requirement !== null && ! $requirement->requirement?->allows_multiple_documents) {
            $existing = $application->documents()
                ->where('application_requirement_id', $requirement->getKey())
                ->whereNotIn('status', [DocumentStatus::Withdrawn, DocumentStatus::Superseded])
                ->exists();

            if ($existing) {
                throw DocumentException::invalidFile('A document already exists for this requirement.');
            }
        }

        $document = null;
        $writtenDisk = null;
        $writtenPath = null;

        try {
            DB::beginTransaction();

            $type = $documentType !== null
                ? DocumentType::from($documentType)
                : ($requirement !== null ? DocumentType::RequirementDocument : DocumentType::ApplicationDocument);

            $document = Document::create([
                'application_id' => $application->id,
                'application_requirement_id' => $requirement?->getKey(),
                'document_type' => $type,
                'status' => DocumentStatus::Uploaded,
                'created_by' => $user->id,
            ]);

            $meta = $this->storage->store($file, $application->id, $document->id);
            $writtenDisk = $meta['storage_disk'];
            $writtenPath = $meta['storage_path'];

            $version = $this->createVersion($document, $user, $meta, 1);

            $document->update([
                'current_version_id' => $version->id,
                'status' => DocumentStatus::Uploaded,
            ]);

            $this->audit->log(
                AuditAction::DocumentUploaded,
                $user,
                $document,
                $application->institution_id,
                [
                    'application_id' => $application->id,
                    'institution_id' => $application->institution_id,
                    'document_version_id' => $version->id,
                    'status_before' => null,
                    'status_after' => DocumentStatus::Uploaded->value,
                    'original_filename' => $meta['original_filename'],
                ],
            );

            DB::commit();
        } catch (Throwable $e) {
            if ($writtenDisk !== null && $writtenPath !== null) {
                $this->storage->deleteFile($writtenDisk, $writtenPath);
            }
            DB::rollBack();

            throw $e instanceof DocumentException
                ? $e
                : DocumentException::uploadFailed($e->getMessage());
        }

        return $document;
    }

    /**
     * Upload a replacement for a rejected or replacement-requested document.
     *
     * The previous version becomes SUPERSEDED and a new version is created as
     * UPLOADED; the prior review history is preserved. The old file is never
     * overwritten.
     *
     * @throws DocumentException
     */
    public function replace(User $user, Document $document, UploadedFile $file): Document
    {
        if (! $document->acceptsReplacement()) {
            throw DocumentException::notReplaceable();
        }

        $application = $document->application;
        $previousVersion = $document->currentVersion;
        $nextNumber = ($document->versions()->max('version_number') ?? 0) + 1;

        $writtenDisk = null;
        $writtenPath = null;

        try {
            DB::beginTransaction();

            if ($previousVersion !== null) {
                $previousVersion->update(['superseded_at' => now()]);
            }

            $meta = $this->storage->store($file, $application->id, $document->id);
            $writtenDisk = $meta['storage_disk'];
            $writtenPath = $meta['storage_path'];

            $version = $this->createVersion($document, $user, $meta, $nextNumber);

            $document->update([
                'current_version_id' => $version->id,
                'status' => DocumentStatus::Uploaded,
                'requested_by' => null,
                'requested_at' => null,
            ]);

            $this->audit->log(
                AuditAction::DocumentReplacementUploaded,
                $user,
                $document,
                $application->institution_id,
                [
                    'application_id' => $application->id,
                    'institution_id' => $application->institution_id,
                    'document_version_id' => $version->id,
                    'superseded_version_id' => $previousVersion?->id,
                    'status_before' => $document->getOriginal('status'),
                    'status_after' => DocumentStatus::Uploaded->value,
                    'original_filename' => $meta['original_filename'],
                ],
            );

            DB::commit();
        } catch (Throwable $e) {
            if ($writtenDisk !== null && $writtenPath !== null) {
                $this->storage->deleteFile($writtenDisk, $writtenPath);
            }
            DB::rollBack();

            throw $e instanceof DocumentException
                ? $e
                : DocumentException::uploadFailed($e->getMessage());
        }

        return $document;
    }

    /**
     * Reviewer marks the document as under review.
     */
    public function startReview(User $user, Document $document): Document
    {
        $this->assertReviewable($document);

        if ($document->status === DocumentStatus::UnderReview) {
            return $document;
        }

        return $this->transition($user, $document, DocumentStatus::UnderReview, AuditAction::DocumentReviewStarted, 'Review started.');
    }

    /**
     * Apply a reviewer decision (accept / reject / request replacement / return
     * to review). Comments are required for reject, replacement request, and
     * return-to-review.
     *
     * @throws DocumentException
     */
    public function review(
        User $user,
        Document $document,
        DocumentDecision $decision,
        ?string $comment = null,
    ): Document {
        $this->assertReviewable($document);

        if ($decision->requiresComment() && blank($comment)) {
            throw DocumentException::commentRequired();
        }

        $version = $document->currentVersion;
        if ($version === null) {
            throw DocumentException::notReviewable();
        }

        $action = match ($decision) {
            DocumentDecision::Accepted => AuditAction::DocumentAccepted,
            DocumentDecision::Rejected => AuditAction::DocumentRejected,
            DocumentDecision::ReplacementRequested => AuditAction::DocumentReplacementRequested,
            DocumentDecision::ReturnedToReview => AuditAction::DocumentReturnedToReview,
        };

        $previousStatus = $document->status;

        try {
            DB::beginTransaction();

            DocumentReview::create([
                'document_id' => $document->id,
                'document_version_id' => $version->id,
                'reviewer_id' => $user->id,
                'decision' => $decision,
                'comment' => $comment,
                'reviewed_at' => now(),
            ]);

            $document->update([
                'status' => $decision->resultingStatus(),
                'last_reviewed_at' => now(),
                'requested_by' => $decision === DocumentDecision::ReplacementRequested ? $user->id : $document->requested_by,
                'requested_at' => $decision === DocumentDecision::ReplacementRequested ? now() : $document->requested_at,
            ]);

            $this->audit->log(
                $action,
                $user,
                $document,
                $document->application->institution_id,
                [
                    'application_id' => $document->application_id,
                    'institution_id' => $document->application->institution_id,
                    'document_version_id' => $version->id,
                    'status_before' => $previousStatus->value,
                    'status_after' => $decision->resultingStatus()->value,
                    'comment' => $comment,
                ],
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e instanceof DocumentException
                ? $e
                : DocumentException::uploadFailed($e->getMessage());
        }

        return $document;
    }

    /**
     * Logically withdraw a document (never physically deleted).
     */
    public function withdraw(User $user, Document $document): Document
    {
        if ($document->isWithdrawn()) {
            return $document;
        }

        return $this->transition($user, $document, DocumentStatus::Withdrawn, AuditAction::DocumentWithdrawn, 'Document withdrawn.');
    }

    /**
     * @throws DocumentException
     */
    private function assertReviewable(Document $document): void
    {
        if ($document->isAccepted()) {
            throw DocumentException::alreadyAccepted();
        }

        if (! $document->isReviewable() && $document->status !== DocumentStatus::UnderReview) {
            throw DocumentException::notReviewable();
        }
    }

    private function transition(
        User $user,
        Document $document,
        DocumentStatus $status,
        AuditAction $action,
        ?string $comment = null,
    ): Document {
        $previous = $document->status;

        try {
            DB::beginTransaction();

            $document->update(['status' => $status, 'last_reviewed_at' => now()]);

            $this->audit->log(
                $action,
                $user,
                $document,
                $document->application->institution_id,
                [
                    'application_id' => $document->application_id,
                    'institution_id' => $document->application->institution_id,
                    'status_before' => $previous->value,
                    'status_after' => $status->value,
                    'comment' => $comment,
                ],
            );

            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();
            throw $e instanceof DocumentException
                ? $e
                : DocumentException::uploadFailed($e->getMessage());
        }

        return $document;
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function createVersion(Document $document, User $user, array $meta, int $number): DocumentVersion
    {
        return DocumentVersion::create([
            'document_id' => $document->id,
            'version_number' => $number,
            'original_filename' => $meta['original_filename'],
            'generated_filename' => $meta['generated_filename'],
            'storage_disk' => $meta['storage_disk'],
            'storage_path' => $meta['storage_path'],
            'mime_type' => $meta['mime_type'],
            'file_extension' => $meta['file_extension'],
            'file_size_bytes' => $meta['file_size_bytes'],
            'sha256_hash' => $meta['sha256_hash'],
            'uploaded_by' => $user->id,
            'uploaded_at' => now(),
        ]);
    }
}
