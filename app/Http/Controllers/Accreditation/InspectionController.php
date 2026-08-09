<?php

namespace App\Http\Controllers\Accreditation;

use App\Enums\ApplicationStatus;
use App\Exceptions\InspectionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Accreditation\CompleteInspectionRequest;
use App\Http\Requests\Accreditation\CreateInspectionRequest;
use App\Http\Requests\Accreditation\InspectionCriterionRequest;
use App\Http\Requests\Accreditation\ReturnInspectionRequest;
use App\Models\AccreditationApplication;
use App\Models\Inspection;
use App\Models\InspectionCriterion;
use App\Models\InspectionEvidence;
use App\Models\InspectionInspector;
use App\Models\InspectionReview;
use App\Models\User;
use App\Services\Accreditation\InspectionEligibilityService;
use App\Services\Accreditation\InspectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Inertia\Response;

class InspectionController extends Controller
{
    public function __construct(
        private readonly InspectionService $inspections,
        private readonly InspectionEligibilityService $eligibility,
    ) {}

    /**
     * Eligible applications list for scheduling, plus the inspector's own
     * inspection schedule.
     */
    public function eligible(Request $request): Response
    {
        $this->authorize('create', Inspection::class);

        $user = $request->user();

        $query = AccreditationApplication::query()
            ->with(['institution', 'program', 'inspections' => fn ($q) => $q->latest()]);

        if (! $user->canScheduleInspection()) {
            // Institution users see only their own institution's applications.
            $query->whereIn('institution_id', $user->institutionIds());
        }

        $applications = $query->where('status', ApplicationStatus::CompleteForInspection)
            ->orderByDesc('updated_at')
            ->get();

        return inertia('accreditation/inspections/eligible', [
            'applications' => $applications->map(function (AccreditationApplication $app): array {
                $active = $app->inspections()->active()->first();

                return [
                    'id' => $app->id,
                    'application_reference' => $app->application_reference,
                    'institution' => $app->institution?->name,
                    'program' => $app->program?->name,
                    'program_type' => $app->program?->program_type->value,
                    'application_type' => $app->application_type->value,
                    'completed_for_inspection_at' => $app->completed_for_inspection_at?->toDayDateTimeString(),
                    'checklist_version' => $app->checklistVersion?->version_code,
                    'current_inspection_status' => $active?->status?->value,
                    'existing_inspection_at' => $active?->scheduled_start_at?->toDayDateTimeString(),
                ];
            }),
        ]);
    }

    /**
     * Inspection list / calendar view.
     *
     * PSP/CART and auditors see all inspections; an assigned inspector sees only
     * inspections they are assigned to; institution users see only their own
     * institution's inspections. Every row re-checks the view policy downstream.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Inspection::class);

        $user = $request->user();

        $query = Inspection::query()
            ->with([
                'application.institution',
                'application.program',
                'inspectors' => fn ($q) => $q->with('user'),
            ])
            ->latest('scheduled_start_at');

        if ($user->isInspector()) {
            $query->whereHas('inspectors', fn ($q) => $q->where('user_id', $user->id));
        } elseif (! $user->canReviewInspection() && ! $user->isAuditor()) {
            $query->whereHas('application', fn ($q) => $q->whereIn('institution_id', $user->institutionIds()));
        }

        $inspections = $query->get();

        return inertia('accreditation/inspections/index', [
            'inspections' => $inspections->map(function (Inspection $inspection) use ($user): array {
                return [
                    'id' => $inspection->id,
                    'status' => $inspection->status->value,
                    'status_label' => $inspection->status->label(),
                    'inspection_type' => $inspection->inspection_type,
                    'scheduled_start_at' => $inspection->scheduled_start_at?->toDayDateTimeString(),
                    'scheduled_end_at' => $inspection->scheduled_end_at?->toDayDateTimeString(),
                    'timezone' => $inspection->timezone,
                    'location' => $inspection->location,
                    'application_reference' => $inspection->application->application_reference,
                    'institution' => $inspection->application->institution?->name,
                    'program' => $inspection->application->program?->name,
                    'can_view' => $user->can('view', $inspection),
                    'lead_inspector' => $inspection->inspectors
                        ->where('role', 'lead_inspector')
                        ->first()?->user?->name,
                ];
            }),
            'can_create' => $user->can('create', Inspection::class),
        ]);
    }

    /**
     * Create-inspection form for an eligible application, with candidate
     * inspectors scoped to institution-eligible users.
     */
    public function create(Request $request, AccreditationApplication $application): Response
    {
        $this->authorize('create', Inspection::class);

        if (! $this->eligibility->isEligible($application)) {
            abort(403, 'This application is not eligible for inspection.');
        }

        $inspectors = User::query()
            ->whereHas('roles', fn ($q) => $q->whereIn('name', ['inspector', 'psp_administrator', 'cart_reviewer']))
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        return inertia('accreditation/inspections/create', [
            'application' => [
                'id' => $application->id,
                'application_reference' => $application->application_reference,
                'institution' => $application->institution?->name,
                'program' => $application->program?->name,
                'program_type' => $application->program?->program_type->value,
                'application_type' => $application->application_type->value,
                'completed_for_inspection_at' => $application->completed_for_inspection_at?->toDayDateTimeString(),
                'checklist_version' => $application->checklistVersion?->version_code,
            ],
            'inspectors' => $inspectors,
        ]);
    }

    /**
     * Store a new inspection (snapshot + assignment + schedule).
     */
    public function store(CreateInspectionRequest $request): RedirectResponse
    {
        $this->authorize('create', Inspection::class);

        /** @var AccreditationApplication $application */
        $application = AccreditationApplication::findOrFail($request->input('application_id'));

        try {
            $result = $this->inspections->create(
                $request->user(),
                $application,
                $request->validated(),
                (int) $request->input('lead_inspector_id'),
                array_map('intval', (array) $request->input('inspector_ids', [])),
            );
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()
            ->route('inspections.show', $result['inspection'])
            ->with('success', 'Inspection scheduled.');
    }

    /**
     * Inspection detail: schedule, assignments, checklist and summary.
     */
    public function show(Request $request, Inspection $inspection): Response
    {
        $this->authorize('view', $inspection);

        $inspection->load([
            'application.institution',
            'application.program',
            'application.checklistVersion',
            'creator',
            'scheduler',
            'inspectors.user',
            'criteria.evidence.document',
            'criteria.reviewer',
            'reviews.reviewer',
        ]);

        $summary = $this->inspections->summary($inspection);

        return inertia('accreditation/inspections/show', [
            'inspection' => $this->present($inspection),
            'summary' => $summary,
            'history' => $inspection->reviews()->get()->map(fn (InspectionReview $row): array => [
                'id' => $row->id,
                'reviewer' => $row->reviewer?->name,
                'status_before' => $row->status_before,
                'status_after' => $row->status_after,
                'summary' => $row->summary,
                'comment' => $row->comment,
                'created_at' => $row->created_at?->toDayDateTimeString(),
            ]),
            'can_start' => $request->user()->can('start', $inspection),
            'can_update_criterion' => $request->user()->can('updateCriterion', $inspection),
            'can_submit' => $request->user()->can('submit', $inspection),
            'can_return' => $request->user()->can('return', $inspection),
            'can_complete' => $request->user()->can('complete', $inspection),
            'can_cancel' => $request->user()->can('cancel', $inspection),
            'can_manage_evidence' => $request->user()->can('manageEvidence', $inspection),
        ]);
    }

    public function start(Request $request, Inspection $inspection): RedirectResponse
    {
        $this->authorize('start', $inspection);

        try {
            $this->inspections->start($request->user(), $inspection);
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspection started.');
    }

    public function saveCriterion(InspectionCriterionRequest $request, Inspection $inspection, InspectionCriterion $criterion): RedirectResponse
    {
        $this->authorize('updateCriterion', $inspection);

        try {
            $this->inspections->saveCriterion($request->user(), $inspection, $criterion, $request->validated());
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Criterion saved.');
    }

    public function submit(Request $request, Inspection $inspection): RedirectResponse
    {
        $this->authorize('submit', $inspection);

        try {
            $this->inspections->submit($request->user(), $inspection);
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspection submitted for review.');
    }

    public function returnForRevision(ReturnInspectionRequest $request, Inspection $inspection): RedirectResponse
    {
        $this->authorize('return', $inspection);

        try {
            $this->inspections->returnForRevision($request->user(), $inspection, $request->input('comment'));
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspection returned for revision.');
    }

    public function complete(CompleteInspectionRequest $request, Inspection $inspection): RedirectResponse
    {
        $this->authorize('complete', $inspection);

        try {
            $this->inspections->complete($request->user(), $inspection, $request->input('summary'));
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspection completed.');
    }

    public function cancel(Request $request, Inspection $inspection): RedirectResponse
    {
        $this->authorize('cancel', $inspection);

        try {
            $this->inspections->cancel($request->user(), $inspection, (string) $request->input('cancellation_reason'));
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspection cancelled.');
    }

    public function reschedule(Request $request, Inspection $inspection): RedirectResponse
    {
        $this->authorize('reschedule', $inspection);

        try {
            $this->inspections->reschedule($request->user(), $inspection, $request->validate([
                'scheduled_start_at' => ['required', 'date'],
                'scheduled_end_at' => ['required', 'date', 'after:scheduled_start_at'],
                'timezone' => ['sometimes', 'string'],
                'location' => ['nullable', 'string'],
                'meeting_url' => ['nullable', 'url'],
                'instructions' => ['nullable', 'string'],
            ]));
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspection rescheduled.');
    }

    public function addInspector(Request $request, Inspection $inspection): RedirectResponse
    {
        $this->authorize('assignInspectors', $inspection);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['sometimes', 'in:lead_inspector,inspector,observer'],
        ]);

        try {
            $this->inspections->addInspector($request->user(), $inspection, (int) $data['user_id'], $data['role'] ?? 'inspector');
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspector added.');
    }

    public function removeInspector(Request $request, Inspection $inspection, InspectionInspector $assignment): RedirectResponse
    {
        $this->authorize('assignInspectors', $inspection);

        try {
            $this->inspections->removeInspector($request->user(), $inspection, $assignment);
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Inspector removed.');
    }

    public function changeLead(Request $request, Inspection $inspection): RedirectResponse
    {
        $this->authorize('assignInspectors', $inspection);

        $data = $request->validate(['user_id' => ['required', 'integer', 'exists:users,id']]);

        try {
            $this->inspections->changeLeadInspector($request->user(), $inspection, (int) $data['user_id']);
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Lead inspector updated.');
    }

    public function linkEvidence(Request $request, Inspection $inspection, InspectionCriterion $criterion): RedirectResponse
    {
        $this->authorize('manageEvidence', $inspection);

        $data = $request->validate([
            'document_id' => ['required', 'integer', 'exists:documents,id'],
            'note' => ['nullable', 'string'],
        ]);

        try {
            $this->inspections->linkEvidence($request->user(), $inspection, $criterion, (int) $data['document_id'], $data['note'] ?? null);
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Evidence linked.');
    }

    public function unlinkEvidence(Request $request, Inspection $inspection, InspectionEvidence $evidence): RedirectResponse
    {
        $this->authorize('manageEvidence', $inspection);

        try {
            $this->inspections->unlinkEvidence($request->user(), $inspection, $evidence);
        } catch (InspectionException $e) {
            throw ValidationException::withMessages([$e->field() => $e->getMessage()]);
        }

        return redirect()->route('inspections.show', $inspection)->with('success', 'Evidence unlinked.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Inspection $inspection): array
    {
        return [
            'id' => $inspection->id,
            'status' => $inspection->status?->value,
            'status_label' => $inspection->status?->label(),
            'inspection_type' => $inspection->inspection_type,
            'application' => [
                'id' => $inspection->application->id,
                'application_reference' => $inspection->application->application_reference,
                'institution' => $inspection->application->institution?->name,
                'program' => $inspection->application->program?->name,
            ],
            'checklist_version' => $inspection->checklistVersion?->version_code,
            'scheduled_start_at' => $inspection->scheduled_start_at?->toDayDateTimeString(),
            'scheduled_end_at' => $inspection->scheduled_end_at?->toDayDateTimeString(),
            'timezone' => $inspection->timezone,
            'location' => $inspection->location,
            'meeting_url' => $inspection->meeting_url,
            'instructions' => $inspection->instructions,
            'created_by' => $inspection->creator?->name,
            'scheduled_by' => $inspection->scheduler?->name,
            'started_at' => $inspection->started_at?->toDayDateTimeString(),
            'submitted_at' => $inspection->submitted_at?->toDayDateTimeString(),
            'completed_at' => $inspection->completed_at?->toDayDateTimeString(),
            'cancelled_at' => $inspection->cancelled_at?->toDayDateTimeString(),
            'cancellation_reason' => $inspection->cancellation_reason,
            'inspectors' => $inspection->inspectors()->get()->map(fn (InspectionInspector $i): array => [
                'id' => $i->id,
                'user_id' => $i->user_id,
                'name' => $i->user->name,
                'role' => $i->role,
                'status' => $i->status,
            ]),
            'criteria' => $this->buildCriteria($inspection),
        ];
    }

    /**
     * Build the grouped, presentation-ready checklist snapshot.
     *
     * @return array<int, mixed>
     */
    private function buildCriteria(Inspection $inspection): array
    {
        return $inspection->criteria()->get()->groupBy('section_name')->map(
            function (Collection $group, $section): array {
                /** @var Collection<int, InspectionCriterion> $group */
                return [
                    'section_name' => $section,
                    'items' => $group->map(fn (InspectionCriterion $c): array => [
                        'id' => $c->id,
                        'requirement_code' => $c->requirement_code,
                        'title' => $c->title,
                        'description' => $c->description,
                        'severity' => $c->severity,
                        'response_type' => $c->response_type,
                        'result' => $c->result?->value,
                        'inspector_notes' => $c->inspector_notes,
                        'institution_response' => $c->institution_response,
                        'reviewed_at' => $c->reviewed_at?->toDayDateTimeString(),
                        'reviewed_by' => $c->reviewer?->name,
                        'evidence' => $c->evidence()->get()->map(fn (InspectionEvidence $e): array => [
                            'id' => $e->id,
                            'document_id' => $e->document_id,
                            'note' => $e->note,
                            'filename' => $e->document?->currentVersion->original_filename ?? null,
                        ])->all(),
                    ])->all(),
                ];
            },
        )->values()->all();
    }
}
