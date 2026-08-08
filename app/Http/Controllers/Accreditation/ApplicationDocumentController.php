<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\DocumentStatus;
use App\Exceptions\DocumentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Documents\UploadDocumentRequest;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Models\Document;
use App\Services\Documents\DocumentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Applicant-facing supporting-document management for an accreditation
 * application.
 *
 * Every action re-checks the policy against the bound application/document, so
 * a guessed URL id cannot touch another institution's data. Files are streamed
 * through controlled responses; the storage path is never exposed.
 */
class ApplicationDocumentController extends Controller
{
    public function __construct(
        private readonly DocumentService $documents,
    ) {}

    /**
     * Applicant document checklist for the application.
     */
    public function index(Request $request, AccreditationApplication $application): Response
    {
        $this->authorize('view', $application);

        $application->load(['documents.currentVersion', 'documents.applicationRequirement.requirement', 'requirements.requirement']);

        return Inertia::render('accreditation/applications/documents/index', [
            'application' => [
                'id' => $application->id,
                'application_reference' => $application->application_reference,
                'status' => $application->status->value,
            ],
            'documents' => $application->documents->map(fn (Document $doc): array => $this->present($doc)),
            'requirements' => $application->requirements
                ->filter(fn (ApplicationRequirement $row): bool => ($row->requirement->requires_document ?? false))
                ->map(fn (ApplicationRequirement $row): array => [
                    'id' => $row->id,
                    'code' => $row->requirement->code,
                    'title' => $row->requirement->requirement,
                    'is_mandatory' => (bool) $row->requirement->is_mandatory,
                    'allows_multiple_documents' => (bool) ($row->requirement->allows_multiple_documents ?? false),
                    'has_document' => $row->documents()->whereNotIn('status', [DocumentStatus::Withdrawn, DocumentStatus::Superseded])->exists(),
                ]),
        ]);
    }

    /**
     * Document detail with version history (authorised users only).
     */
    public function show(Request $request, AccreditationApplication $application, Document $document): Response
    {
        $this->authorize('view', $application);
        $this->authorize('view', $document);
        $this->ensureBelongsToApplication($application, $document);

        $document->load(['versions.uploader', 'reviews.reviewer', 'applicationRequirement.requirement', 'currentVersion']);

        return Inertia::render('accreditation/applications/documents/show', [
            'application' => ['id' => $application->id, 'application_reference' => $application->application_reference],
            'document' => $this->present($document, true),
            'can_replace' => $request->user()->can('replace', $document),
            'can_withdraw' => $request->user()->can('withdraw', $document),
        ]);
    }

    /**
     * Store an uploaded document (linked to a requirement when provided).
     */
    public function store(UploadDocumentRequest $request, AccreditationApplication $application): RedirectResponse
    {
        $this->authorize('update', $application);

        $requirement = $this->resolveRequirement($application, $request->input('application_requirement_id'));

        try {
            $this->documents->upload(
                $request->user(),
                $application,
                $request->file('document'),
                $requirement,
            );
        } catch (DocumentException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('success', 'Document uploaded.');
    }

    /**
     * Upload a replacement for a rejected / replacement-requested document.
     */
    public function replace(UploadDocumentRequest $request, AccreditationApplication $application, Document $document): RedirectResponse
    {
        $this->authorize('update', $application);
        $this->authorize('replace', $document);
        $this->ensureBelongsToApplication($application, $document);

        try {
            $this->documents->replace($request->user(), $document, $request->file('document'));
        } catch (DocumentException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('success', 'Replacement document uploaded.');
    }

    /**
     * Logically withdraw a document.
     */
    public function withdraw(Request $request, AccreditationApplication $application, Document $document): RedirectResponse
    {
        $this->authorize('update', $application);
        $this->authorize('withdraw', $document);
        $this->ensureBelongsToApplication($application, $document);

        try {
            $this->documents->withdraw($request->user(), $document);
        } catch (DocumentException $e) {
            return back()->withErrors(['document' => $e->getMessage()]);
        }

        return back()->with('success', 'Document withdrawn.');
    }

    /**
     * Secure download — file streamed through a policy-checked response.
     */
    public function download(Request $request, AccreditationApplication $application, Document $document): StreamedResponse
    {
        // Document-level policy enforces institution isolation for applicants
        // and cross-institution remit for reviewers; the application binding is
        // re-checked below. We intentionally do NOT require AccreditationApplicationPolicy::view
        // here, because reviewers must reach documents on draft applications.
        $this->authorize('download', $document);
        $this->ensureBelongsToApplication($application, $document);

        $version = $document->currentVersion;
        abort_if($version === null || $version->isSuperseded(), 404);

        return Storage::disk($version->storage_disk)->download(
            $version->storage_path,
            $version->original_filename,
        );
    }

    /**
     * Secure inline preview — file streamed with the correct content type.
     */
    public function preview(Request $request, AccreditationApplication $application, Document $document): StreamedResponse
    {
        $this->authorize('preview', $document);
        $this->ensureBelongsToApplication($application, $document);

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
            'document_type' => $document->document_type->value,
            'requirement_code' => $document->applicationRequirement?->requirement->code,
            'requirement_title' => $document->applicationRequirement?->requirement->requirement,
            'is_mandatory' => (bool) ($document->applicationRequirement?->requirement->is_mandatory ?? false),
            'filename' => $version?->original_filename,
            'uploaded_at' => $version?->uploaded_at->toDayDateTimeString(),
            'uploaded_by' => $version?->uploader?->name,
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

    private function resolveRequirement(AccreditationApplication $application, ?string $id): ?ApplicationRequirement
    {
        if ($id === null) {
            return null;
        }

        /** @var ApplicationRequirement|null $requirement */
        $requirement = $application->requirements()->find($id);

        return $requirement;
    }

    private function ensureBelongsToApplication(AccreditationApplication $application, Document $document): void
    {
        abort_if($document->application_id !== $application->id, 404);
    }
}
