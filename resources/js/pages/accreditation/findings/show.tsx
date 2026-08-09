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
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import findings from '@/routes/findings';
import inspections from '@/routes/inspections';
import type { BreadcrumbItem } from '@/types';

interface HistoryEntry {
    id: number;
    action: string;
    action_label: string;
    status_before: string | null;
    status_after: string | null;
    comment: string | null;
    reviewer: string | null;
    created_at: string | null;
}

interface VersionEntry {
    version_number: number;
    proposed_action: string;
    implementation_notes: string | null;
    evidence: Array<{ document_id: number; note: string | null }> | null;
    submitted_at: string | null;
    submitter: string | null;
}

interface ActionEntry {
    id: number;
    action_reference: string;
    title: string;
    description: string;
    responsible_user: string | null;
    responsible_role: string | null;
    due_date: string | null;
    status: string;
    status_label: string;
    is_overdue: boolean;
    submitted_at: string | null;
    accepted_at: string | null;
    rejected_at: string | null;
    rejection_reason: string | null;
    closed_at: string | null;
    versions: VersionEntry[];
}

interface CriterionInfo {
    id: number;
    section_name: string;
    requirement_code: string;
    title: string;
    severity: string;
    result: string | null;
}

interface FindingData {
    id: number;
    finding_reference: string;
    title: string;
    description: string | null;
    category: string;
    severity: string;
    status: string;
    status_label: string;
    due_date: string | null;
    is_overdue: boolean;
    source_result: string | null;
    source_section_name: string | null;
    source_requirement_code: string | null;
    source_requirement_title: string | null;
    source_requirement_description: string | null;
    source_inspector_notes: string | null;
    source_institution_response: string | null;
    institution_acknowledged_at: string | null;
    resolved_at: string | null;
    closed_at: string | null;
    closed_by: string | null;
    waived_at: string | null;
    waived_by: string | null;
    waived_reason: string | null;
    voided_at: string | null;
    voided_by: string | null;
    void_reason: string | null;
    application_reference: string | null;
    institution: string | null;
    inspection_id: number;
    criterion: CriterionInfo | null;
    creator: string | null;
    corrective_actions: ActionEntry[];
}

interface Props {
    finding: FindingData;
    history: HistoryEntry[];
    categories: Record<string, string>;
    can_manage: boolean;
    can_acknowledge: boolean;
    can_respond: boolean;
    can_review: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Findings', href: findings.index().url },
    { title: 'Detail', href: '#' },
];

const statusVariant = (
    status: string,
): 'default' | 'secondary' | 'outline' | 'destructive' => {
    switch (status) {
        case 'open':
        case 'acknowledged':
        case 'corrective_action_required':
            return 'default';
        case 'under_review':
            return 'secondary';
        case 'resolved':
        case 'closed':
            return 'outline';
        default:
            return 'destructive';
    }
};

export default function FindingShow({
    finding,
    history,
    categories,
    can_manage,
    can_acknowledge,
    can_respond,
    can_review,
}: Props) {
    const actionForm = useForm({
        comment: '',
        void_reason: '',
        rejection_reason: '',
    });

    const postAction = (name: string, url: string) => {
        actionForm.post(url, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Finding ${finding.finding_reference}`} />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            {finding.finding_reference}: {finding.title}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {finding.institution} ·{' '}
                            {finding.application_reference} · Classified as{' '}
                            <span className="capitalize">
                                {categories[finding.category] ??
                                    finding.category}
                            </span>
                        </p>
                    </div>
                    <Badge variant={statusVariant(finding.status)}>
                        {finding.status_label}
                    </Badge>
                </div>

                {/* Action bar */}
                <div className="flex flex-wrap gap-2">
                    {can_manage && finding.status === 'draft' && (
                        <Button
                            onClick={() =>
                                postAction(
                                    'open',
                                    findings.open({ finding: finding.id }).url,
                                )
                            }
                        >
                            Open
                        </Button>
                    )}
                    {can_acknowledge && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                postAction(
                                    'acknowledge',
                                    findings.acknowledge({
                                        finding: finding.id,
                                    }).url,
                                )
                            }
                        >
                            Acknowledge
                        </Button>
                    )}
                    {can_manage && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                postAction(
                                    'require',
                                    findings.requireCorrectiveAction({
                                        finding: finding.id,
                                    }).url,
                                )
                            }
                        >
                            Require corrective action
                        </Button>
                    )}
                    {can_review && (
                        <>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    postAction(
                                        'close',
                                        findings.close({ finding: finding.id })
                                            .url,
                                    )
                                }
                            >
                                Close
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() =>
                                    postAction(
                                        'waive',
                                        findings.waive({ finding: finding.id })
                                            .url,
                                    )
                                }
                            >
                                Waive
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() =>
                                    postAction(
                                        'void',
                                        findings.void({ finding: finding.id })
                                            .url,
                                    )
                                }
                            >
                                Void
                            </Button>
                        </>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Finding details</CardTitle>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2 text-sm">
                            <div>
                                <span className="font-medium">Severity: </span>
                                {finding.severity}
                            </div>
                            <div>
                                <span className="font-medium">
                                    Description:{' '}
                                </span>
                                {finding.description ?? '—'}
                            </div>
                            <div>
                                <span className="font-medium">Due: </span>
                                {finding.due_date ? (
                                    <span
                                        className={
                                            finding.is_overdue
                                                ? 'font-medium text-destructive'
                                                : ''
                                        }
                                    >
                                        {finding.due_date}
                                        {finding.is_overdue ? ' (overdue)' : ''}
                                    </span>
                                ) : (
                                    '—'
                                )}
                            </div>
                            <div>
                                <span className="font-medium">
                                    Created by:{' '}
                                </span>
                                {finding.creator ?? '—'}
                            </div>
                            {finding.resolved_at && (
                                <div>
                                    <span className="font-medium">
                                        Resolved:{' '}
                                    </span>
                                    {finding.resolved_at}
                                </div>
                            )}
                            {finding.closed_at && (
                                <div>
                                    <span className="font-medium">
                                        Closed:{' '}
                                    </span>
                                    {finding.closed_at} by {finding.closed_by}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>
                                Source context (immutable snapshot)
                            </CardTitle>
                            <CardDescription>
                                Copied at creation; reflects the inspection as
                                it was.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2 text-sm">
                            {finding.criterion ? (
                                <div>
                                    <span className="font-medium">
                                        Criterion:{' '}
                                    </span>
                                    {finding.criterion.section_name} ·{' '}
                                    {finding.criterion?.requirement_code}
                                </div>
                            ) : (
                                <div>
                                    <span className="font-medium">
                                        Criterion:{' '}
                                    </span>
                                    —
                                </div>
                            )}
                            <div>
                                <span className="font-medium">Result: </span>
                                {finding.source_result ?? '—'}
                            </div>
                            <div>
                                <span className="font-medium">
                                    Requirement:{' '}
                                </span>
                                {finding.source_requirement_title ?? '—'}
                            </div>
                            {finding.source_requirement_description && (
                                <div>
                                    <span className="font-medium">
                                        Requirement description:{' '}
                                    </span>
                                    {finding.source_requirement_description}
                                </div>
                            )}
                            {finding.source_inspector_notes && (
                                <div>
                                    <span className="font-medium">
                                        Inspector notes:{' '}
                                    </span>
                                    {finding.source_inspector_notes}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                {/* Corrective actions */}
                <Card>
                    <CardHeader>
                        <CardTitle>Corrective actions</CardTitle>
                        <CardDescription>
                            {finding.corrective_actions.length} attached.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        {can_manage && (
                            <CorrectiveActionForm findingId={finding.id} />
                        )}

                        {finding.corrective_actions.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No corrective actions yet.
                            </p>
                        ) : (
                            finding.corrective_actions.map((action) => (
                                <div
                                    key={action.id}
                                    className="rounded-md border p-4"
                                >
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <div className="font-medium">
                                                {action.action_reference}:{' '}
                                                {action.title}
                                            </div>
                                            <div className="text-sm text-muted-foreground">
                                                {action.responsible_user ??
                                                    action.responsible_role ??
                                                    'Unassigned'}
                                                {action.due_date &&
                                                    ` · due ${action.due_date}${action.is_overdue ? ' (overdue)' : ''}`}
                                            </div>
                                        </div>
                                        <Badge
                                            variant={statusVariant(
                                                action.status,
                                            )}
                                        >
                                            {action.status_label}
                                        </Badge>
                                    </div>

                                    <div className="mt-2 text-sm">
                                        {action.description}
                                    </div>

                                    {action.rejection_reason && (
                                        <div className="mt-2 rounded bg-destructive/10 p-2 text-sm text-destructive">
                                            Returned: {action.rejection_reason}
                                        </div>
                                    )}

                                    {action.versions.length > 0 && (
                                        <div className="mt-3 flex flex-col gap-2">
                                            <div className="text-xs font-medium text-muted-foreground uppercase">
                                                Submitted responses
                                            </div>
                                            {action.versions.map((v) => (
                                                <div
                                                    key={v.version_number}
                                                    className="rounded bg-muted/40 p-2 text-sm"
                                                >
                                                    <div className="font-medium">
                                                        v{v.version_number} —{' '}
                                                        {v.submitted_at} by{' '}
                                                        {v.submitter ?? '—'}
                                                    </div>
                                                    <div>
                                                        {v.proposed_action}
                                                    </div>
                                                    {v.implementation_notes && (
                                                        <div className="text-muted-foreground">
                                                            {
                                                                v.implementation_notes
                                                            }
                                                        </div>
                                                    )}
                                                    {v.evidence &&
                                                        v.evidence.length >
                                                            0 && (
                                                            <div className="mt-1 text-xs text-muted-foreground">
                                                                {
                                                                    v.evidence
                                                                        .length
                                                                }{' '}
                                                                evidence file(s)
                                                                attached.
                                                            </div>
                                                        )}
                                                </div>
                                            ))}
                                        </div>
                                    )}

                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {can_respond &&
                                            (action.status === 'open' ||
                                                action.status ===
                                                    'in_progress') && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        postAction(
                                                            'start',
                                                            findings.actions.start(
                                                                {
                                                                    finding:
                                                                        finding.id,
                                                                    action: action.id,
                                                                },
                                                            ).url,
                                                        )
                                                    }
                                                >
                                                    Start
                                                </Button>
                                            )}
                                        {can_respond &&
                                            action.status === 'in_progress' && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        postAction(
                                                            'submit_action',
                                                            findings.actions.submit(
                                                                {
                                                                    finding:
                                                                        finding.id,
                                                                    action: action.id,
                                                                },
                                                            ).url,
                                                        )
                                                    }
                                                >
                                                    Submit response
                                                </Button>
                                            )}
                                        {can_review &&
                                            action.status ===
                                                'under_review' && (
                                                <>
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            postAction(
                                                                'accept',
                                                                findings.actions.accept(
                                                                    {
                                                                        finding:
                                                                            finding.id,
                                                                        action: action.id,
                                                                    },
                                                                ).url,
                                                            )
                                                        }
                                                    >
                                                        Accept
                                                    </Button>
                                                    <Button
                                                        size="sm"
                                                        variant="destructive"
                                                        onClick={() =>
                                                            postAction(
                                                                'reject',
                                                                findings.actions.reject(
                                                                    {
                                                                        finding:
                                                                            finding.id,
                                                                        action: action.id,
                                                                    },
                                                                ).url,
                                                            )
                                                        }
                                                    >
                                                        Reject
                                                    </Button>
                                                </>
                                            )}
                                        {can_review &&
                                            action.status === 'accepted' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        postAction(
                                                            'close_action',
                                                            findings.actions.close(
                                                                {
                                                                    finding:
                                                                        finding.id,
                                                                    action: action.id,
                                                                },
                                                            ).url,
                                                        )
                                                    }
                                                >
                                                    Close action
                                                </Button>
                                            )}
                                        {can_respond &&
                                            (action.status === 'open' ||
                                                action.status ===
                                                    'in_progress') && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        postAction(
                                                            'withdraw',
                                                            findings.actions.withdraw(
                                                                {
                                                                    finding:
                                                                        finding.id,
                                                                    action: action.id,
                                                                },
                                                            ).url,
                                                        )
                                                    }
                                                >
                                                    Withdraw
                                                </Button>
                                            )}
                                    </div>
                                </div>
                            ))
                        )}
                    </CardContent>
                </Card>

                {/* History */}
                <Card>
                    <CardHeader>
                        <CardTitle>Audit trail</CardTitle>
                        <CardDescription>
                            Complete historical record of review actions.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {history.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No recorded actions yet.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2 text-sm">
                                {history.map((h) => (
                                    <li
                                        key={h.id}
                                        className="flex items-start gap-3 border-b pb-2"
                                    >
                                        <Badge variant="outline">
                                            {h.action_label}
                                        </Badge>
                                        <div>
                                            <div>{h.comment ?? '—'}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {h.reviewer ?? 'system'} ·{' '}
                                                {h.created_at ?? '—'}
                                                {h.status_before &&
                                                    h.status_after && (
                                                        <span>
                                                            {' '}
                                                            ({
                                                                h.status_before
                                                            } → {h.status_after}
                                                            )
                                                        </span>
                                                    )}
                                            </div>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <div>
                    <Link
                        href={
                            inspections.show({
                                inspection: finding.inspection_id,
                            }).url
                        }
                    >
                        <Button variant="outline">Back to inspection</Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}

function CorrectiveActionForm({ findingId }: { findingId: number }) {
    const { data, setData, post, processing, reset } = useForm({
        title: '',
        description: '',
        responsible_role: '' as string,
        due_date: '' as string,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(findings.actions.store({ finding: findingId }).url, {
            preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    return (
        <form onSubmit={submit} className="rounded-md border p-4">
            <div className="mb-2 text-sm font-medium">
                New corrective action
            </div>
            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap">
                <Input
                    placeholder="Title"
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                />
                <Input
                    placeholder="Responsible role"
                    value={data.responsible_role}
                    onChange={(e) =>
                        setData('responsible_role', e.target.value)
                    }
                />
                <Input
                    type="date"
                    value={data.due_date}
                    onChange={(e) => setData('due_date', e.target.value)}
                />
                <Button type="submit" disabled={processing} size="sm">
                    Add action
                </Button>
            </div>
            <Textarea
                className="mt-2"
                placeholder="Description"
                value={data.description}
                onChange={(e) => setData('description', e.target.value)}
            />
        </form>
    );
}
