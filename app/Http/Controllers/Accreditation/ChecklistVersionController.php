<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\ChecklistVersionStatus;
use App\Enums\ProgramType;
use App\Enums\RequirementResponseType;
use App\Enums\RequirementSeverity;
use App\Exceptions\ChecklistVersionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Checklist\PublishChecklistVersionRequest;
use App\Http\Requests\Checklist\RetireChecklistVersionRequest;
use App\Http\Requests\Checklist\StoreChecklistVersionRequest;
use App\Http\Requests\Checklist\StoreRequirementRequest;
use App\Http\Requests\Checklist\StoreSectionRequest;
use App\Http\Requests\Checklist\UpdateChecklistVersionRequest;
use App\Http\Requests\Checklist\UpdateRequirementRequest;
use App\Http\Requests\Checklist\UpdateSectionRequest;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use App\Services\ChecklistContentService;
use App\Services\ChecklistVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Administrator-only management of the configurable accreditation checklist.
 *
 * Every action re-authorizes through ChecklistVersionPolicy so a guessed URL
 * id cannot mutate a version. PUBLISHED and RETIRED versions are read-only;
 * their editor view renders in read-only mode with a "clone" affordance.
 */
class ChecklistVersionController extends Controller
{
    public function __construct(
        private readonly ChecklistVersionService $versions,
        private readonly ChecklistContentService $content,
    ) {}

    /**
     * List all checklist versions with summary counts and filters.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', ChecklistVersion::class);

        $query = ChecklistVersion::query()->with(['creator', 'publisher', 'retirer']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('program_type')) {
            $query->forProgramType(ProgramType::from($request->string('program_type')->value()));
        }

        if ($request->filled('version_code')) {
            $query->where('version_code', 'like', '%'.$request->string('version_code').'%');
        }

        $versions = $query->orderByDesc('created_at')->get()->map(fn (ChecklistVersion $v): array => [
            'id' => $v->id,
            'version_code' => $v->version_code,
            'name' => $v->name,
            'program_type' => $v->program_type->value,
            'program_type_label' => $v->program_type->label(),
            'status' => $v->status->value,
            'status_label' => $v->status->label(),
            'effective_from' => $v->effective_from?->toDateString(),
            'effective_until' => $v->effective_until?->toDateString(),
            'section_count' => $v->sections()->count(),
            'active_requirement_count' => $v->sections()->whereHas('requirements', fn ($q) => $q->where('checklist_requirements.is_active', true))->count()
                ? $v->requirements()->where('checklist_requirements.is_active', true)->count() : 0,
            'created_by' => $v->creator?->name,
            'published_at' => $v->published_at?->toDayDateTimeString(),
            'retired_at' => $v->retired_at?->toDayDateTimeString(),
        ]);

        return inertia('accreditation/admin/checklists/index', [
            'versions' => $versions,
            'filters' => $request->only(['status', 'program_type', 'version_code']),
            'statuses' => array_map(
                fn (ChecklistVersionStatus $s): array => ['value' => $s->value, 'label' => $s->label()],
                ChecklistVersionStatus::cases(),
            ),
            'program_types' => array_map(
                fn (ProgramType $p): array => ['value' => $p->value, 'label' => $p->label()],
                ProgramType::cases(),
            ),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', ChecklistVersion::class);

        return inertia('accreditation/admin/checklists/create', [
            'program_types' => array_map(
                fn (ProgramType $p): array => ['value' => $p->value, 'label' => $p->label()],
                ProgramType::cases(),
            ),
        ]);
    }

    public function store(StoreChecklistVersionRequest $request): RedirectResponse
    {
        $version = $this->versions->createDraft($request->user(), $request->validated());

        return redirect()
            ->route('admin.checklists.edit', $version)
            ->with('success', 'Draft checklist version '.$version->version_code.' created.');
    }

    /**
     * Detail view; renders read-only for PUBLISHED/RETIRED, editable for DRAFT.
     */
    public function show(ChecklistVersion $checklistVersion): Response
    {
        $this->authorize('view', $checklistVersion);

        return inertia('accreditation/admin/checklists/show', $this->presentVersion($checklistVersion));
    }

    public function edit(ChecklistVersion $checklistVersion): Response
    {
        $this->authorize('update', $checklistVersion);

        return inertia('accreditation/admin/checklists/edit', $this->presentVersion($checklistVersion));
    }

    public function update(UpdateChecklistVersionRequest $request, ChecklistVersion $checklistVersion): RedirectResponse
    {
        $checklistVersion->update($request->validated());

        return redirect()
            ->route('admin.checklists.edit', $checklistVersion)
            ->with('success', 'Checklist version metadata updated.');
    }

    public function clone(Request $request, ChecklistVersion $checklistVersion): RedirectResponse
    {
        $this->authorize('clone', $checklistVersion);

        $clone = $this->versions->clone($request->user(), $checklistVersion, [
            'version_code' => $request->string('version_code', $checklistVersion->version_code.'-copy'),
            'name' => $request->string('name', $checklistVersion->name.' (copy)'),
        ]);

        return redirect()
            ->route('admin.checklists.edit', $clone)
            ->with('success', 'Cloned to new draft '.$clone->version_code.'.');
    }

    /**
     * Publish confirmation page (shows any blocking errors).
     */
    public function publishConfirm(ChecklistVersion $checklistVersion): Response
    {
        $this->authorize('publish', $checklistVersion);

        return inertia('accreditation/admin/checklists/publish', [
            'version' => $this->presentVersion($checklistVersion),
            'publishing_errors' => $this->versions->publishingErrors($checklistVersion),
        ]);
    }

    public function publish(PublishChecklistVersionRequest $request, ChecklistVersion $checklistVersion): RedirectResponse
    {
        try {
            $published = $this->versions->publish(
                $request->user(),
                $checklistVersion,
                $request->string('reason')->value() ?: null,
            );
        } catch (ChecklistVersionException $e) {
            return redirect()
                ->route('admin.checklists.publish.confirm', $checklistVersion)
                ->withErrors(['publish' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.checklists.show', $published)
            ->with('success', 'Checklist version '.$published->version_code.' published.');
    }

    public function retireConfirm(ChecklistVersion $checklistVersion): Response
    {
        $this->authorize('retire', $checklistVersion);

        return inertia('accreditation/admin/checklists/retire', [
            'version' => $this->presentVersion($checklistVersion),
        ]);
    }

    public function retire(RetireChecklistVersionRequest $request, ChecklistVersion $checklistVersion): RedirectResponse
    {
        try {
            $retired = $this->versions->retire(
                $request->user(),
                $checklistVersion,
                $request->string('reason')->value() ?: null,
            );
        } catch (ChecklistVersionException $e) {
            return redirect()
                ->route('admin.checklists.retire.confirm', $checklistVersion)
                ->withErrors(['retire' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.checklists.show', $retired)
            ->with('success', 'Checklist version '.$retired->version_code.' retired.');
    }

    /**
     * Read-only applicant-facing preview of a version.
     */
    public function preview(ChecklistVersion $checklistVersion): Response
    {
        $this->authorize('preview', $checklistVersion);

        return inertia('accreditation/admin/checklists/preview', [
            'version' => $this->presentVersion($checklistVersion),
            'preview' => $checklistVersion->outline(),
        ]);
    }

    public function destroy(Request $request, ChecklistVersion $checklistVersion): RedirectResponse
    {
        $this->authorize('delete', $checklistVersion);

        $this->versions->deleteDraft($request->user(), $checklistVersion);

        return redirect()
            ->route('admin.checklists.index')
            ->with('success', 'Draft checklist version deleted.');
    }

    // --- Sections ---------------------------------------------------------

    public function storeSection(StoreSectionRequest $request, ChecklistVersion $checklistVersion): RedirectResponse
    {
        $this->content->createSection($request->user(), $checklistVersion, $request->validated());

        return back()->with('success', 'Section added.');
    }

    public function updateSection(UpdateSectionRequest $request, ChecklistVersion $checklistVersion, ChecklistSection $section): RedirectResponse
    {
        $this->content->updateSection($request->user(), $section, $request->validated());

        return back()->with('success', 'Section updated.');
    }

    public function reorderSections(Request $request, ChecklistVersion $checklistVersion): RedirectResponse
    {
        $this->authorize('manageSections', $checklistVersion);

        $ordered = $request->input('order', []);
        $this->content->reorderSections($request->user(), $checklistVersion, array_map('intval', (array) $ordered));

        return back()->with('success', 'Section order updated.');
    }

    public function deactivateSection(Request $request, ChecklistVersion $checklistVersion, ChecklistSection $section): RedirectResponse
    {
        $this->authorize('manageSections', $checklistVersion);

        $active = $request->boolean('is_active', true);
        $this->content->deactivateSection($request->user(), $section, $active);

        return back()->with('success', $active ? 'Section reactivated.' : 'Section deactivated.');
    }

    public function destroySection(Request $request, ChecklistVersion $checklistVersion, ChecklistSection $section): RedirectResponse
    {
        $this->authorize('manageSections', $checklistVersion);

        $this->content->deactivateSection($request->user(), $section, false);
        $section->requirements()->delete();
        $section->delete();

        return back()->with('success', 'Section removed.');
    }

    // --- Requirements -----------------------------------------------------

    public function storeRequirement(StoreRequirementRequest $request, ChecklistVersion $checklistVersion): RedirectResponse
    {
        $this->content->createRequirement($request->user(), $checklistVersion, $request->validated());

        return back()->with('success', 'Requirement added.');
    }

    public function updateRequirement(UpdateRequirementRequest $request, ChecklistVersion $checklistVersion, ChecklistRequirement $requirement): RedirectResponse
    {
        $this->content->updateRequirement($request->user(), $requirement, $request->validated());

        return back()->with('success', 'Requirement updated.');
    }

    public function reorderRequirements(Request $request, ChecklistVersion $checklistVersion, ChecklistSection $section): RedirectResponse
    {
        $this->authorize('manageRequirements', $checklistVersion);

        $ordered = $request->input('order', []);
        $this->content->reorderRequirements($request->user(), $section, array_map('intval', (array) $ordered));

        return back()->with('success', 'Requirement order updated.');
    }

    public function deactivateRequirement(Request $request, ChecklistVersion $checklistVersion, ChecklistRequirement $requirement): RedirectResponse
    {
        $this->authorize('manageRequirements', $checklistVersion);

        $active = $request->boolean('is_active', true);
        $this->content->deactivateRequirement($request->user(), $requirement, $active);

        return back()->with('success', $active ? 'Requirement reactivated.' : 'Requirement deactivated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function presentVersion(ChecklistVersion $version): array
    {
        $version->load(['sections.requirements', 'creator', 'publisher', 'retirer']);

        return [
            'id' => $version->id,
            'version_code' => $version->version_code,
            'name' => $version->name,
            'description' => $version->description,
            'program_type' => $version->program_type->value,
            'program_type_label' => $version->program_type->label(),
            'status' => $version->status->value,
            'status_label' => $version->status->label(),
            'is_editable' => $version->isEditable(),
            'effective_from' => $version->effective_from?->toDateString(),
            'effective_until' => $version->effective_until?->toDateString(),
            'created_by' => $version->creator?->name,
            'published_at' => $version->published_at?->toDayDateTimeString(),
            'published_by' => $version->publisher?->name,
            'retired_at' => $version->retired_at?->toDayDateTimeString(),
            'retired_by' => $version->retirer?->name,
            'can_edit' => request()->user()?->can('update', $version) ?? false,
            'can_publish' => request()->user()?->can('publish', $version) ?? false,
            'can_retire' => request()->user()?->can('retire', $version) ?? false,
            'can_clone' => request()->user()?->can('clone', $version) ?? false,
            'can_delete' => request()->user()?->can('delete', $version) ?? false,
            'can_preview' => request()->user()?->can('preview', $version) ?? false,
            'sections' => $version->sections()->orderBy('position')->get()->map(fn (ChecklistSection $s): array => [
                'id' => $s->id,
                'code' => $s->code,
                'title' => $s->title,
                'description' => $s->description,
                'position' => $s->position,
                'is_active' => $s->is_active,
                'requirements' => $s->requirements()->orderBy('position')->get()->map(fn (ChecklistRequirement $r): array => [
                    'id' => $r->id,
                    'code' => $r->code,
                    'requirement' => $r->requirement,
                    'guidance' => $r->guidance,
                    'response_type' => $r->response_type->value,
                    'applicable_program_types' => $r->applicable_program_types,
                    'severity' => $r->severity->value,
                    'severity_label' => $r->severity->label(),
                    'is_mandatory' => $r->is_mandatory,
                    'is_reviewer_only' => $r->is_reviewer_only,
                    'requires_document' => $r->requires_document,
                    'position' => $r->position,
                    'is_active' => $r->is_active,
                ])->values()->all(),
            ])->values()->all(),
            'enums' => [
                'response_types' => array_map(
                    fn (RequirementResponseType $t): array => ['value' => $t->value, 'label' => $t->label()],
                    RequirementResponseType::cases(),
                ),
                'severities' => array_map(
                    fn (RequirementSeverity $s): array => ['value' => $s->value, 'label' => $s->label()],
                    RequirementSeverity::cases(),
                ),
                'program_types' => array_map(
                    fn (ProgramType $p): array => ['value' => $p->value, 'label' => $p->label()],
                    ProgramType::cases(),
                ),
            ],
        ];
    }
}
