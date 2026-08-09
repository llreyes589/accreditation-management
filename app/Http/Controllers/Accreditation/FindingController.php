<?php

namespace App\Http\Controllers\Accreditation;

use App\Exceptions\FindingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accreditation\CreateCorrectiveActionRequest;
use App\Http\Requests\Accreditation\CreateFindingRequest;
use App\Models\CorrectiveAction;
use App\Models\Finding;
use App\Models\Inspection;
use App\Models\InspectionCriterion;
use App\Services\Accreditation\CorrectiveActionService;
use App\Services\Accreditation\FindingService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class FindingController extends Controller
{
    public function __construct(
        private readonly FindingService $findings,
        private readonly CorrectiveActionService $actions,
    ) {}

    /**
     * Findings dashboard. PSP/CART and auditors see all; institution users see
     * only their own institution's findings.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Finding::class);

        $user = $request->user();

        $query = Finding::query()
            ->with(['application.institution', 'inspection', 'correctiveActions'])
            ->latest();

        if (! $user->canReviewInspection() && ! $user->isAuditor()) {
            $query->whereHas('application', fn ($q) => $q->whereIn('institution_id', $user->institutionIds()));
        }

        $now = now();

        /** @var Collection<int, Finding> $findings */
        $findings = $query->get();

        return inertia('accreditation/findings/index', [
            'findings' => $findings->map(function (Finding $finding) use ($user, $now): array {
                return [
                    'id' => $finding->id,
                    'finding_reference' => $finding->finding_reference,
                    'title' => $finding->title,
                    'category' => $finding->category,
                    'severity' => $finding->severity,
                    'status' => $finding->status->value,
                    'status_label' => $finding->status->label(),
                    'due_date' => $finding->due_date?->toDayDateTimeString(),
                    'is_overdue' => $finding->isOverdue($now),
                    'application_reference' => $finding->application->application_reference,
                    'institution' => $finding->application->institution?->name,
                    'corrective_action_count' => $finding->correctiveActions->count(),
                    'can_view' => $user->can('view', $finding),
                ];
            }),
            'can_create' => $user->can('create', Finding::class),
            'categories' => config('findings.categories'),
        ]);
    }

    /**
     * Findings raised from a specific inspection.
     */
    public function forInspection(Request $request, Inspection $inspection): Response
    {
        $this->authorize('view', $inspection);

        $user = $request->user();

        /** @var Collection<int, Finding> $findings */
        $findings = $inspection->application->findings()
            ->with(['correctiveActions'])
            ->latest()
            ->get();

        return inertia('accreditation/findings/index', [
            'findings' => $findings->map(function (Finding $finding) use ($user): array {
                return [
                    'id' => $finding->id,
                    'finding_reference' => $finding->finding_reference,
                    'title' => $finding->title,
                    'category' => $finding->category,
                    'severity' => $finding->severity,
                    'status' => $finding->status->value,
                    'status_label' => $finding->status->label(),
                    'due_date' => $finding->due_date?->toDayDateTimeString(),
                    'is_overdue' => $finding->isOverdue(),
                    'application_reference' => $finding->application->application_reference,
                    'institution' => $finding->application->institution?->name,
                    'corrective_action_count' => $finding->correctiveActions->count(),
                    'can_view' => $user->can('view', $finding),
                ];
            }),
            'can_create' => $user->can('create', Finding::class),
            'categories' => config('findings.categories'),
            'inspection_id' => $inspection->id,
        ]);
    }

    /**
     * Create-finding form for an inspection criterion.
     */
    public function create(Request $request, Inspection $inspection, ?InspectionCriterion $criterion = null): Response
    {
        $this->authorize('create', Finding::class);

        if ($criterion !== null && $criterion->inspection_id !== $inspection->id) {
            abort(404);
        }

        if (! $this->findings->isCreatable($inspection, $criterion)) {
            abort(403, 'This inspection is not in a state that permits findings.');
        }

        return inertia('accreditation/findings/create', [
            'inspection_id' => $inspection->id,
            'criterion' => $criterion === null ? null : [
                'id' => $criterion->id,
                'section_name' => $criterion->section_name,
                'requirement_code' => $criterion->requirement_code,
                'title' => $criterion->title,
                'description' => $criterion->description,
                'severity' => $criterion->severity,
                'result' => $criterion->result?->value,
                'inspector_notes' => $criterion->inspector_notes,
            ],
            'categories' => config('findings.categories'),
        ]);
    }

    public function store(CreateFindingRequest $request): RedirectResponse
    {
        $this->authorize('create', Finding::class);

        /** @var Inspection|null $inspection */
        $inspection = Inspection::find($request->input('inspection_id'));

        if ($inspection === null) {
            throw ValidationException::withMessages(['inspection_id' => 'The selected inspection does not exist.']);
        }

        $criterion = null;
        if ($request->filled('inspection_criterion_id')) {
            /** @var InspectionCriterion|null $criterion */
            $criterion = InspectionCriterion::find($request->input('inspection_criterion_id'));
        }

        try {
            $finding = $this->findings->create(
                $request->user(),
                $inspection,
                $request->validated(),
                $criterion,
            );
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()
            ->route('findings.show', $finding)
            ->with('success', 'Finding created.');
    }

    public function show(Request $request, Finding $finding): Response
    {
        $this->authorize('view', $finding);

        $finding->load([
            'application.institution',
            'inspection',
            'criterion.evidence.document',
            'creator',
            'acknowledgedBy',
            'closedBy',
            'waivedBy',
            'voidedBy',
            'correctiveActions.versions.submitter',
            'correctiveActions.responsibleUser',
            'correctiveActions.submittedBy',
            'correctiveActions.acceptedBy',
            'reviews.reviewer',
        ]);

        $now = now();

        return inertia('accreditation/findings/show', [
            'finding' => $this->present($finding, $now),
            'history' => $finding->reviews()->get()->map(fn ($row) => [
                'id' => $row->id,
                'action' => $row->action->value,
                'action_label' => $row->action->label(),
                'status_before' => $row->status_before,
                'status_after' => $row->status_after,
                'comment' => $row->comment,
                'reviewer' => $row->reviewer?->name,
                'created_at' => $row->created_at?->toDayDateTimeString(),
            ]),
            'categories' => config('findings.categories'),
            'can_manage' => $request->user()->can('manage', $finding),
            'can_acknowledge' => $request->user()->can('acknowledge', $finding),
            'can_respond' => $request->user()->can('respond', $finding),
            'can_review' => $request->user()->can('review', $finding),
        ]);
    }

    public function open(Request $request, Finding $finding): RedirectResponse
    {
        $this->authorize('manage', $finding);
        $this->transition(fn () => $this->findings->open($request->user(), $finding), 'Finding opened.');

        return redirect()->route('findings.show', $finding);
    }

    public function acknowledge(Request $request, Finding $finding): RedirectResponse
    {
        $this->authorize('acknowledge', $finding);
        $this->transition(fn () => $this->findings->acknowledge($request->user(), $finding), 'Finding acknowledged.');

        return redirect()->route('findings.show', $finding);
    }

    public function requireCorrectiveAction(Request $request, Finding $finding): RedirectResponse
    {
        $this->authorize('manage', $finding);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_role' => ['nullable', 'string', 'in:institution_administrator,training_officer,consultant'],
            'due_date' => ['sometimes', 'date'],
        ]);

        try {
            $this->findings->requireCorrectiveAction($request->user(), $finding, $data ?: null);
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Corrective action required.');
    }

    public function close(Request $request, Finding $finding): RedirectResponse
    {
        $this->authorize('review', $finding);

        $data = $request->validate([
            'summary' => ['required', 'string', 'min:5'],
        ]);

        try {
            $this->findings->close($request->user(), $finding, $data['summary']);
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Finding closed.');
    }

    public function reopen(Request $request, Finding $finding): RedirectResponse
    {
        $this->authorize('review', $finding);

        $data = $request->validate(['comment' => ['required', 'string']]);

        try {
            $this->findings->reopen($request->user(), $finding, $data['comment']);
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Finding reopened.');
    }

    public function waive(Request $request, Finding $finding): RedirectResponse
    {
        $this->authorize('manage', $finding);

        $data = $request->validate(['void_reason' => ['required', 'string']]);

        try {
            $this->findings->waive($request->user(), $finding, $data['void_reason']);
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Finding waived.');
    }

    public function void(Request $request, Finding $finding): RedirectResponse
    {
        $this->authorize('manage', $finding);

        $data = $request->validate(['void_reason' => ['required', 'string']]);

        try {
            $this->findings->void($request->user(), $finding, $data['void_reason']);
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Finding voided.');
    }

    // --- Corrective actions (nested under a finding) ---

    public function storeAction(CreateCorrectiveActionRequest $request, Finding $finding): RedirectResponse
    {
        $this->authorize('manage', $finding);

        try {
            $this->actions->create($request->user(), $finding, $request->validated());
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Corrective action created.');
    }

    public function startAction(Request $request, Finding $finding, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('respond', $finding);
        $this->actionTransition(fn () => $this->actions->start($request->user(), $action), 'Corrective action started.');

        return redirect()->route('findings.show', $finding);
    }

    public function submitAction(Request $request, Finding $finding, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('respond', $finding);

        $data = $request->validate([
            'proposed_action' => ['required', 'string'],
            'implementation_notes' => ['nullable', 'string'],
            'evidence' => ['nullable', 'array'],
            'evidence.*.document_id' => ['required_with:evidence', 'integer', 'exists:documents,id'],
            'evidence.*.note' => ['nullable', 'string'],
        ]);

        try {
            $this->actions->submit($request->user(), $action, $data);
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Response submitted.');
    }

    public function acceptAction(Request $request, Finding $finding, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('review', $finding);

        try {
            $this->actions->accept($request->user(), $action);
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Corrective action accepted.');
    }

    public function rejectAction(Request $request, Finding $finding, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('review', $finding);

        $data = $request->validate(['rejection_reason' => ['required', 'string']]);

        try {
            $this->actions->reject($request->user(), $action, $data['rejection_reason']);
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('findings.show', $finding)->with('success', 'Corrective action rejected.');
    }

    public function withdrawAction(Request $request, Finding $finding, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('respond', $finding);
        $this->actionTransition(fn () => $this->actions->withdraw($request->user(), $action), 'Corrective action withdrawn.');

        return redirect()->route('findings.show', $finding);
    }

    public function closeAction(Request $request, Finding $finding, CorrectiveAction $action): RedirectResponse
    {
        $this->authorize('review', $finding);
        $this->actionTransition(fn () => $this->actions->close($request->user(), $action), 'Corrective action closed.');

        return redirect()->route('findings.show', $finding);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Finding $finding, \DateTimeInterface $now): array
    {
        return [
            'id' => $finding->id,
            'finding_reference' => $finding->finding_reference,
            'title' => $finding->title,
            'description' => $finding->description,
            'category' => $finding->category,
            'severity' => $finding->severity,
            'status' => $finding->status->value,
            'status_label' => $finding->status->label(),
            'due_date' => $finding->due_date?->toDayDateTimeString(),
            'is_overdue' => $finding->isOverdue($now),
            'source_result' => $finding->source_result,
            'source_section_name' => $finding->source_section_name,
            'source_requirement_code' => $finding->source_requirement_code,
            'source_requirement_title' => $finding->source_requirement_title,
            'source_requirement_description' => $finding->source_requirement_description,
            'source_inspector_notes' => $finding->source_inspector_notes,
            'source_institution_response' => $finding->source_institution_response,
            'institution_acknowledged_at' => $finding->institution_acknowledged_at?->toDayDateTimeString(),
            'resolved_at' => $finding->resolved_at?->toDayDateTimeString(),
            'closed_at' => $finding->closed_at?->toDayDateTimeString(),
            'closed_by' => $finding->closedBy?->name,
            'waived_at' => $finding->waived_at?->toDayDateTimeString(),
            'waived_by' => $finding->waivedBy?->name,
            'waived_reason' => $finding->void_reason,
            'voided_at' => $finding->voided_at?->toDayDateTimeString(),
            'voided_by' => $finding->voidedBy?->name,
            'void_reason' => $finding->void_reason,
            'application_reference' => $finding->application->application_reference,
            'institution' => $finding->application->institution?->name,
            'inspection_id' => $finding->inspection_id,
            'criterion' => $finding->criterion === null ? null : [
                'id' => $finding->criterion->id,
                'section_name' => $finding->criterion->section_name,
                'requirement_code' => $finding->criterion->requirement_code,
                'title' => $finding->criterion->title,
                'severity' => $finding->criterion->severity,
                'result' => $finding->criterion->result?->value,
            ],
            'creator' => $finding->creator?->name,
            'corrective_actions' => $finding->correctiveActions()->get()->map(fn ($action) => $this->presentAction($action)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentAction(CorrectiveAction $action): array
    {
        return [
            'id' => $action->id,
            'action_reference' => $action->action_reference,
            'title' => $action->title,
            'description' => $action->description,
            'responsible_user' => $action->responsibleUser?->name,
            'responsible_role' => $action->responsible_role,
            'due_date' => $action->due_date?->toDayDateTimeString(),
            'status' => $action->status->value,
            'status_label' => $action->status->label(),
            'is_overdue' => $action->isOverdue(),
            'submitted_at' => $action->submitted_at?->toDayDateTimeString(),
            'accepted_at' => $action->accepted_at?->toDayDateTimeString(),
            'rejected_at' => $action->rejected_at?->toDayDateTimeString(),
            'rejection_reason' => $action->rejection_reason,
            'closed_at' => $action->closed_at?->toDayDateTimeString(),
            'versions' => $action->versions()->get()->map(fn ($v) => [
                'version_number' => $v->version_number,
                'proposed_action' => $v->proposed_action,
                'implementation_notes' => $v->implementation_notes,
                'evidence' => $v->evidence,
                'submitted_at' => $v->submitted_at?->toDayDateTimeString(),
                'submitter' => $v->submitter?->name,
            ]),
        ];
    }

    private function transition(callable $fn, string $message): void
    {
        try {
            $fn();
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }
    }

    private function actionTransition(callable $fn, string $message): void
    {
        try {
            $fn();
        } catch (FindingException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }
    }
}
