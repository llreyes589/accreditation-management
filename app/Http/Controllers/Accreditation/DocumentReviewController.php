<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\DocumentStatus;
use App\Exceptions\DocumentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\ReviewDocumentRequest;
use App\Models\AccreditationApplication;
use App\Models\Document;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reviewer-facing document review.
 *
 * Only authorized PSP/CART reviewers may change review status (enforced in the
 * policy and service). Downloads and previews are policy-gated and streamed;
 * the storage path is never exposed.
 */
class DocumentReviewController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {}

    /**
     * Reviewer list of documents for an application.
     */
    public function index(Request $request, AccreditationApplication $application): Response
    {
        $this->authorize('view', $application);

        $application->load(['documents.currentVersion.uploader', 'documents.applicationRequirement.requirement', 'institution', 'program']);
        $documents = $application->documents()
            ->with(['currentVersion.uploader', 'applicationRequirement.requirement'])
            ->get();

        return Inertia::render('accreditation/reviewer/documents/index', [
            'application' => [
                'id' => $application->id,
                'application_reference' => $application->application_reference,
                'institution' => $application->institution?->name,
                'program' => $application->program?->name,
            ],
            'documents' => $documents->map(fn (Document $doc): array => $this->present($doc)),
        ]);
    }

    /**
     * Single document review page.
     */
    public function show(Request $request, Document $document): Response
    {
        $this->authorize('view', $document);
        $document->load(['application.institution', 'application.program', 'versions.uploader', 'reviews.reviewer', 'applicationRequirement.requirement', 'currentVersion']);

        return Inertia::render('accreditation/reviewer/documents/show', [
            'application' => [
                'id' => $document->application_id,
                'application_reference' => $document->application->application_reference,
                'institution' => $document->application->institution?->name,
                'program' => $document->application->program?->name,
            ],
            'document' => $this->present($document, true),
            'decisions' => [
                ['value' => 'accepted', 'label' => 'Accept'],
                ['value' => 'rejected', 'label' => 'Reject'],
                ['value' => 'replacement_requested', 'label' => 'Request Replacement'],
                ['value' => 'returned_to_review', 'label' => 'Return to Review'],
            ],
            'can_review' => $request->user()->can('review', $document),
        ]);
    }

    /**
     * Start reviewing (move to UNDER_REVIEW).
     */
    public function startReview(Request $request, Document $document): RedirectResponse
    {
        $this->authorize('startReview', $document);

        try {
            $this->documents->startReview($request->user(), $document);
        } catch (DocumentException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('success', 'Review started.');
    }

    /**
     * Record a reviewer decision (accept / reject / request replacement /
     * return to review).
     */
    public function review(ReviewDocumentRequest $request, Document $document): RedirectResponse
    {
        $this->authorize('review', $document);

        try {
            $this->documents->review(
                $request->user(),
                $document,
                $request->decision(),
                $request->input('comment'),
            );
        } catch (DocumentException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('success', 'Decision recorded.');
    }

    /**
     * Secure download for reviewers.
     */
    public function download(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('download', $document);

        $version = $document->currentVersion;
        abort_if($version === null || $version->isSuperseded(), 404);

        return Storage::disk($version->storage_disk)->download(
            $version->storage_path,
            $version->original_filename,
        );
    }

    /**
     * Secure inline preview for reviewers.
     */
    public function preview(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('preview', $document);

        $version = $document->currentVersion;
        abort_if($version === null || $version->isSuperseded(), 404);

        return Storage::disk($version->storage_disk)->response(
            $version->storage_path,
            $version->original_filename,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Document $document, bool $withHistory = false): array
    {
        $version = $document->currentVersion;

        $data = [
            'id' => $document->id,
            'status' => $document->status->value,
            'status_label' => $document->status->label(),
            'requirement_code' => $document->applicationRequirement?->requirement?->code,
            'requirement_title' => $document->applicationRequirement?->requirement?->requirement,
            'filename' => $version?->original_filename,
            'mime_type' => $version?->mime_type,
            'file_extension' => $version?->file_extension,
            'file_size_bytes' => $version?->file_size_bytes,
            'uploaded_by' => $version?->uploader?->name,
            'uploaded_at' => $version?->uploaded_at?->toDayDateTimeString(),
            'latest_comment' => $document->reviews->last()?->comment,
            'replacement_needed' => $document->status === DocumentStatus::ReplacementRequested,
        ];

        if ($withHistory) {
            $data['versions'] = $document->versions->map(fn ($v): array => [
                'version_number' => $v->version_number,
                'original_filename' => $v->original_filename,
                'mime_type' => $v->mime_type,
                'file_size_bytes' => $v->file_size_bytes,
                'uploaded_at' => $v->uploaded_at->toDayDateTimeString(),
                'uploaded_by' => $v->uploader?->name,
                'is_current' => $v->id === $document->current_version_id,
                'is_superseded' => $v->isSuperseded(),
            ]);
            $data['reviews'] = $document->reviews->map(fn ($r): array => [
                'decision' => $r->decision->value,
                'decision_label' => $r->decision->label(),
                'comment' => $r->comment,
                'reviewer' => $r->reviewer?->name,
                'reviewed_at' => $r->reviewed_at->toDayDateTimeString(),
            ]);
        }

        return $data;
    }
}
