import { Head, Link, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

interface InspectionCriterionItem {
    id: number;
    requirement_code: string | null;
    title: string;
    description: string | null;
    severity: string | null;
    response_type: string | null;
    result: string | null;
    inspector_notes: string | null;
    institution_response: string | null;
    reviewed_at: string | null;
    reviewed_by: string | null;
    evidence: {
        id: number;
        document_id: number | null;
        note: string | null;
        filename: string | null;
    }[];
}

interface InspectionSection {
    section_name: string | null;
    items: InspectionCriterionItem[];
}

interface InspectionProps {
    inspection: {
        id: number;
        status: string;
        status_label: string;
        inspection_type: string;
        application: {
            id: number;
            application_reference: string;
            institution: string | null;
            program: string | null;
        };
        checklist_version: string | null;
        scheduled_start_at: string | null;
        scheduled_end_at: string | null;
        timezone: string | null;
        location: string | null;
        meeting_url: string | null;
        instructions: string | null;
        created_by: string | null;
        scheduled_by: string | null;
        started_at: string | null;
        submitted_at: string | null;
        completed_at: string | null;
        cancelled_at: string | null;
        cancellation_reason: string | null;
        inspectors: {
            id: number;
            user_id: number;
            name: string | null;
            role: string;
            status: string;
        }[];
        criteria: InspectionSection[];
    };
    summary: {
        total: number;
        compliant: number;
        partially_compliant: number;
        noncompliant: number;
        not_applicable: number;
        not_observed: number;
        not_started: number;
        completed: number;
        unresolved_notes: number;
        is_complete: boolean;
    };
    history: {
        id: number;
        reviewer: string | null;
        status_before: string | null;
        status_after: string | null;
        summary: string | null;
        comment: string | null;
        created_at: string | null;
    }[];
    can_start: boolean;
    can_update_criterion: boolean;
    can_submit: boolean;
    can_return: boolean;
    can_complete: boolean;
    can_cancel: boolean;
    can_manage_evidence: boolean;
}

const RESULT_OPTIONS = [
    { value: 'compliant', label: 'Compliant' },
    { value: 'partially_compliant', label: 'Partially Compliant' },
    { value: 'noncompliant', label: 'Non-Compliant' },
    { value: 'not_applicable', label: 'Not Applicable' },
    { value: 'not_observed', label: 'Not Observed' },
];

export default function InspectionShow({
    inspection,
    summary,
    history,
    can_start,
    can_update_criterion,
    can_submit,
    can_return,
    can_complete,
    can_cancel,
    can_manage_evidence,
}: InspectionProps) {
    const startForm = useForm({});
    const submitForm = useForm({});
    const returnForm = useForm({ comment: '' });
    const completeForm = useForm({ summary: '' });

    function start() {
        startForm.post(`/inspections/${inspection.id}/start`);
    }

    function submitInspection() {
        submitForm.post(`/inspections/${inspection.id}/submit`);
    }

    function returnInspection() {
        returnForm.post(`/inspections/${inspection.id}/return`);
    }

    function completeInspection() {
        completeForm.post(`/inspections/${inspection.id}/complete`);
    }

    return (
        <>
            <Head title={`Inspection ${inspection.id}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Inspection {inspection.id}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {inspection.application.application_reference} —{' '}
                            {inspection.application.institution} /{' '}
                            {inspection.application.program}
                        </p>
                    </div>
                    <Badge variant="outline">{inspection.status_label}</Badge>
                </div>

                <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle>Schedule</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <div>
                                Type:{' '}
                                <span className="capitalize">
                                    {inspection.inspection_type}
                                </span>
                            </div>
                            <div>
                                Checklist: {inspection.checklist_version ?? '—'}
                            </div>
                            <div>
                                Start: {inspection.scheduled_start_at ?? '—'}
                            </div>
                            <div>End: {inspection.scheduled_end_at ?? '—'}</div>
                            <div>Timezone: {inspection.timezone ?? '—'}</div>
                            <div>Location: {inspection.location ?? '—'}</div>
                            <div>
                                Meeting URL: {inspection.meeting_url ?? '—'}
                            </div>
                            {inspection.instructions && (
                                <div className="pt-2 text-muted-foreground">
                                    {inspection.instructions}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Summary</CardTitle>
                            <CardDescription>
                                Snapshot checklist results
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <div>Total criteria: {summary.total}</div>
                            <div>Completed: {summary.completed}</div>
                            <div>Compliant: {summary.compliant}</div>
                            <div>
                                Partially compliant:{' '}
                                {summary.partially_compliant}
                            </div>
                            <div>Non-compliant: {summary.noncompliant}</div>
                            <div>Not applicable: {summary.not_applicable}</div>
                            <div>Not observed: {summary.not_observed}</div>
                            <div>Not started: {summary.not_started}</div>
                            <div>
                                Unresolved notes: {summary.unresolved_notes}
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Inspectors</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            {inspection.inspectors.map((i) => (
                                <div
                                    key={i.id}
                                    className="flex items-center justify-between"
                                >
                                    <span>
                                        {i.name ?? '—'}{' '}
                                        <span className="text-xs text-muted-foreground">
                                            ({i.role.replace(/_/g, ' ')})
                                        </span>
                                    </span>
                                    <Badge
                                        variant={
                                            i.status === 'accepted'
                                                ? 'secondary'
                                                : 'outline'
                                        }
                                    >
                                        {i.status}
                                    </Badge>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Checklist</CardTitle>
                        <CardDescription>
                            Sectioned snapshot. Results, notes and evidence are
                            recorded here and cannot change the source
                            checklist.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {inspection.criteria.map((section, idx) => (
                            <div key={idx}>
                                <h3 className="mb-2 text-sm font-semibold">
                                    {section.section_name ?? 'Section'}
                                </h3>
                                <div className="space-y-3">
                                    {section.items.map((c) => (
                                        <CriterionRow
                                            key={c.id}
                                            criterion={c}
                                            canEdit={can_update_criterion}
                                            canEvidence={can_manage_evidence}
                                            inspectionId={inspection.id}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Actions</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        {can_start && (
                            <Button
                                onClick={start}
                                disabled={startForm.processing}
                            >
                                Start Inspection
                            </Button>
                        )}

                        {can_submit && (
                            <Button
                                onClick={submitInspection}
                                disabled={submitForm.processing}
                            >
                                Submit for Review
                            </Button>
                        )}

                        {can_return && (
                            <div className="space-y-1">
                                <Textarea
                                    placeholder="Reviewer comment (required to return for revision)"
                                    value={returnForm.data.comment}
                                    onChange={(e) =>
                                        returnForm.setData(
                                            'comment',
                                            e.target.value,
                                        )
                                    }
                                />
                                <Button
                                    variant="outline"
                                    onClick={returnInspection}
                                    disabled={returnForm.processing}
                                >
                                    Return for Revision
                                </Button>
                                {returnForm.errors.comment && (
                                    <p className="text-xs text-destructive">
                                        {returnForm.errors.comment}
                                    </p>
                                )}
                            </div>
                        )}

                        {can_complete && (
                            <div className="space-y-1">
                                <Textarea
                                    placeholder="Reviewer summary (required to complete)"
                                    value={completeForm.data.summary}
                                    onChange={(e) =>
                                        completeForm.setData(
                                            'summary',
                                            e.target.value,
                                        )
                                    }
                                />
                                <Button
                                    onClick={completeInspection}
                                    disabled={completeForm.processing}
                                >
                                    Complete Inspection
                                </Button>
                                {completeForm.errors.summary && (
                                    <p className="text-xs text-destructive">
                                        {completeForm.errors.summary}
                                    </p>
                                )}
                            </div>
                        )}

                        {can_cancel && (
                            <Button variant="destructive" disabled>
                                Cancel Inspection
                            </Button>
                        )}
                    </CardContent>
                </Card>

                {history.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Review History</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-2 text-sm">
                            {history.map((h) => (
                                <div
                                    key={h.id}
                                    className="border-b pb-2 last:border-0"
                                >
                                    <div className="font-medium">
                                        {h.reviewer ?? 'System'}:{' '}
                                        {h.status_before?.replace(/_/g, ' ')} →{' '}
                                        {h.status_after?.replace(/_/g, ' ')}
                                    </div>
                                    {h.summary && (
                                        <div className="text-muted-foreground">
                                            {h.summary}
                                        </div>
                                    )}
                                    {h.comment && (
                                        <div className="text-muted-foreground">
                                            {h.comment}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}

function CriterionRow({
    criterion,
    canEdit,
    canEvidence,
    inspectionId,
}: {
    criterion: InspectionCriterionItem;
    canEdit: boolean;
    canEvidence: boolean;
    inspectionId: number;
}) {
    const form = useForm({
        result: criterion.result ?? 'not_started',
        inspector_notes: criterion.inspector_notes ?? '',
        institution_response: criterion.institution_response ?? '',
    });

    function save() {
        form.post(`/inspections/${inspectionId}/criteria/${criterion.id}`);
    }

    return (
        <div className="rounded border p-3">
            <div className="flex items-start justify-between gap-3">
                <div>
                    <div className="flex items-center gap-2">
                        <span className="font-mono text-xs">
                            {criterion.requirement_code ?? '—'}
                        </span>
                        <span className="text-sm font-medium">
                            {criterion.title}
                        </span>
                        {criterion.severity && (
                            <Badge variant="outline">
                                {criterion.severity}
                            </Badge>
                        )}
                    </div>
                    {criterion.description && (
                        <p className="mt-1 text-xs text-muted-foreground">
                            {criterion.description}
                        </p>
                    )}
                </div>
                <Badge
                    variant={
                        criterion.result === 'noncompliant'
                            ? 'destructive'
                            : 'secondary'
                    }
                >
                    {criterion.result?.replace(/_/g, ' ') ?? 'Not Started'}
                </Badge>
            </div>

            {canEdit && (
                <div className="mt-3 grid grid-cols-1 gap-2 md:grid-cols-3">
                    <select
                        className="rounded border px-2 py-1.5 text-sm"
                        value={form.data.result}
                        onChange={(e) => form.setData('result', e.target.value)}
                    >
                        <option value="not_started">Not Started</option>
                        {RESULT_OPTIONS.map((r) => (
                            <option key={r.value} value={r.value}>
                                {r.label}
                            </option>
                        ))}
                    </select>
                    <Textarea
                        placeholder="Inspector notes"
                        value={form.data.inspector_notes}
                        onChange={(e) =>
                            form.setData('inspector_notes', e.target.value)
                        }
                    />
                    <Textarea
                        placeholder="Institution response"
                        value={form.data.institution_response}
                        onChange={(e) =>
                            form.setData('institution_response', e.target.value)
                        }
                    />
                </div>
            )}

            {canEvidence && (
                <div className="mt-2">
                    <Link
                        href={`/reviewer/documents`}
                        className="text-xs text-muted-foreground underline"
                    >
                        Manage supporting documents
                    </Link>
                </div>
            )}

            {criterion.evidence.length > 0 && (
                <div className="mt-2 text-xs text-muted-foreground">
                    Evidence:{' '}
                    {criterion.evidence
                        .map((e) => e.filename ?? `doc ${e.document_id}`)
                        .join(', ')}
                </div>
            )}

            {canEdit && (
                <Button
                    size="sm"
                    variant="outline"
                    className="mt-2"
                    onClick={save}
                    disabled={form.processing}
                >
                    Save Criterion
                </Button>
            )}
        </div>
    );
}
