import { Head, Link, router, useForm } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Separator } from '@/components/ui/separator';
import { Textarea } from '@/components/ui/textarea';

interface CompletenessSummary {
    checklist_outstanding_mandatory: number;
    documents: {
        required: number;
        satisfied: number;
        missing: number;
        outstanding: number;
        rejected: number;
        replacement_requested: number;
    };
    blocking_item_count: number;
    is_complete: boolean;
    deficiencies: DeficiencyItem[];
}

interface DeficiencyItem {
    category: 'checklist' | 'document';
    code: string;
    title: string;
    state: string;
    detail: string;
}

interface HistoryRow {
    id: number;
    decision: string;
    reviewer: string | null;
    comment: string | null;
    created_at: string | null;
}

interface ApplicationSummary {
    id: number;
    application_reference: string;
    status: string;
    status_label: string;
    institution: string | null;
    program: string | null;
    submitted_at: string | null;
    under_review_by: string | null;
    under_review_at: string | null;
    returned_by: string | null;
    returned_at: string | null;
    returned_comment: string | null;
    completed_for_inspection_by: string | null;
    completed_for_inspection_at: string | null;
}

const DECISION_LABELS: Record<string, string> = {
    under_review: 'Review started',
    returned_for_revision: 'Returned for revision',
    complete_for_inspection: 'Marked complete for inspection',
    resubmitted: 'Resubmitted by applicant',
};

export default function CompletenessShow({
    application,
    summary,
    history,
    can_begin,
    can_return,
    can_complete,
}: {
    application: ApplicationSummary;
    summary: CompletenessSummary;
    history: HistoryRow[];
    can_begin: boolean;
    can_return: boolean;
    can_complete: boolean;
}) {
    const returnForm = useForm({ comment: '' });

    const beginReview = () => {
        router.post(
            `/reviewer/completeness/applications/${application.id}/begin`,
            {},
            { preserveScroll: true },
        );
    };

    const complete = () => {
        router.post(
            `/reviewer/completeness/applications/${application.id}/complete`,
            {},
            { preserveScroll: true },
        );
    };

    const submitReturn = () => {
        returnForm.post(
            `/reviewer/completeness/applications/${application.id}/return`,
            { preserveScroll: true },
        );
    };

    const blocking = summary.blocking_item_count > 0;

    return (
        <>
            <Head title={`Completeness ${application.application_reference}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Button
                            asChild
                            size="sm"
                            variant="ghost"
                            className="mb-1 -ml-2"
                        >
                            <Link href="/reviewer/completeness/applications">
                                ← Back to review queue
                            </Link>
                        </Button>
                        <h1 className="text-2xl font-semibold">
                            {application.application_reference}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {application.institution ?? '—'} ·{' '}
                            {application.program ?? '—'}
                        </p>
                    </div>
                    <Badge variant="outline">{application.status_label}</Badge>
                </div>

                <Alert>
                    <AlertTitle>
                        Completeness review is a documentary check only
                    </AlertTitle>
                    <AlertDescription>
                        It does not determine compliance, accreditation
                        eligibility, or approval, and it does not schedule an
                        inspection.
                    </AlertDescription>
                </Alert>

                {blocking ? (
                    <Alert variant="destructive">
                        <AlertTitle>
                            {summary.blocking_item_count} blocking item(s)
                            remain
                        </AlertTitle>
                        <AlertDescription>
                            Resolve all deficiencies before marking the
                            application complete for inspection.
                        </AlertDescription>
                    </Alert>
                ) : (
                    <Alert>
                        <AlertTitle>No blocking items</AlertTitle>
                        <AlertDescription>
                            The application meets the documentary completeness
                            criteria for inspection.
                        </AlertDescription>
                    </Alert>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Checklist
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm">
                            <p>
                                Outstanding mandatory items:{' '}
                                <span className="font-semibold">
                                    {summary.checklist_outstanding_mandatory}
                                </span>
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Documents
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>
                                Accepted: {summary.documents.satisfied} /{' '}
                                {summary.documents.required}
                            </p>
                            <p>Missing: {summary.documents.missing}</p>
                            <p>Rejected: {summary.documents.rejected}</p>
                            <p>
                                Replacement requested:{' '}
                                {summary.documents.replacement_requested}
                            </p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Review metadata
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-1 text-sm">
                            <p>Submitted: {application.submitted_at ?? '—'}</p>
                            <p>
                                Under review by:{' '}
                                {application.under_review_by ?? '—'}
                            </p>
                            <p>
                                Complete for inspection by:{' '}
                                {application.completed_for_inspection_by ?? '—'}
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Deficiency &amp; missing-item list
                        </CardTitle>
                        <CardDescription>
                            Items that currently block readiness for inspection.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {summary.deficiencies.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No deficiencies. The application is complete.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {summary.deficiencies.map((item, i) => (
                                    <li
                                        key={`${item.category}-${item.code}-${i}`}
                                        className="rounded-md border p-3"
                                    >
                                        <div className="flex items-center gap-2">
                                            <Badge variant="outline">
                                                {item.category}
                                            </Badge>
                                            <span className="font-mono text-xs">
                                                {item.code}
                                            </span>
                                            <span className="font-medium">
                                                {item.title}
                                            </span>
                                        </div>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {item.detail}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                {application.returned_comment && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Reviewer comments on return
                            </CardTitle>
                            <CardDescription>
                                From {application.returned_by ?? 'reviewer'} on{' '}
                                {application.returned_at ?? '—'}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="text-sm">
                            {application.returned_comment}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Reviewer actions
                        </CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {can_begin && (
                            <Button onClick={beginReview}>
                                Begin completeness review
                            </Button>
                        )}

                        {can_return && (
                            <div className="space-y-2">
                                <Textarea
                                    placeholder="Required: explain what the institution must revise (min 5 characters)."
                                    value={returnForm.data.comment}
                                    onChange={(e) =>
                                        returnForm.setData(
                                            'comment',
                                            e.target.value,
                                        )
                                    }
                                />
                                {returnForm.errors.comment && (
                                    <p className="text-sm text-destructive">
                                        {returnForm.errors.comment}
                                    </p>
                                )}
                                <Button
                                    variant="destructive"
                                    disabled={returnForm.processing}
                                    onClick={submitReturn}
                                >
                                    Return for revision
                                </Button>
                            </div>
                        )}

                        {can_complete && (
                            <Button
                                disabled={blocking}
                                onClick={complete}
                                title={
                                    blocking
                                        ? 'Resolve all blocking items first'
                                        : undefined
                                }
                            >
                                Mark complete for inspection
                            </Button>
                        )}

                        {!can_begin && !can_return && !can_complete && (
                            <p className="text-sm text-muted-foreground">
                                No reviewer actions are available in the current
                                status.
                            </p>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Completeness history
                        </CardTitle>
                        <CardDescription>
                            Completeness report — every status transition.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {history.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No completeness-review activity yet.
                            </p>
                        ) : (
                            <ul className="space-y-3">
                                {history.map((row) => (
                                    <li
                                        key={row.id}
                                        className="border-b pb-2 last:border-0"
                                    >
                                        <div className="flex items-center gap-2">
                                            <Badge variant="secondary">
                                                {DECISION_LABELS[
                                                    row.decision
                                                ] ?? row.decision}
                                            </Badge>
                                            <span className="text-xs text-muted-foreground">
                                                {row.created_at ?? ''}
                                            </span>
                                        </div>
                                        {row.comment && (
                                            <p className="mt-1 text-sm">
                                                {row.comment}
                                            </p>
                                        )}
                                        {row.reviewer && (
                                            <p className="text-xs text-muted-foreground">
                                                {row.reviewer}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Separator />
            </div>
        </>
    );
}
