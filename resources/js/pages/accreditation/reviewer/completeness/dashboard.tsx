import { Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface CompletenessApplicationRow {
    id: number;
    application_reference: string;
    status: string;
    status_label: string;
    institution: string | null;
    program: string | null;
    submitted_at: string | null;
    returned_comment: string | null;
    is_complete: boolean;
}

function statusVariant(
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'under_completeness_review') {
        return 'default';
    }

    if (status === 'returned_for_revision') {
        return 'destructive';
    }

    return 'outline';
}

export default function CompletenessDashboard({
    applications,
}: {
    applications: CompletenessApplicationRow[];
}) {
    return (
        <>
            <Head title="Completeness Review" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Completeness Review
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Administrative and documentary check that an application
                        is complete enough to proceed to inspection. This does
                        not determine compliance or accreditation eligibility.
                    </p>
                </div>

                {applications.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nothing to review</CardTitle>
                            <CardDescription>
                                There are no applications awaiting or under
                                completeness review.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <caption className="sr-only">
                                        Applications under completeness review
                                    </caption>
                                    <thead className="border-b bg-muted/50">
                                        <tr>
                                            <th
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                Reference
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                Institution
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                Program
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                Status
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                Completeness
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                Submitted
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-3 text-right font-medium"
                                            >
                                                Actions
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {applications.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-b last:border-0"
                                            >
                                                <td className="p-3 font-mono text-xs">
                                                    {row.application_reference}
                                                </td>
                                                <td className="p-3">
                                                    {row.institution ?? '—'}
                                                </td>
                                                <td className="p-3">
                                                    {row.program ?? '—'}
                                                </td>
                                                <td className="p-3">
                                                    <Badge
                                                        variant={statusVariant(
                                                            row.status,
                                                        )}
                                                    >
                                                        {row.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="p-3">
                                                    {row.is_complete ? (
                                                        <Badge variant="secondary">
                                                            Complete
                                                        </Badge>
                                                    ) : (
                                                        <Badge variant="outline">
                                                            Has deficiencies
                                                        </Badge>
                                                    )}
                                                </td>
                                                <td className="p-3 text-xs text-muted-foreground">
                                                    {row.submitted_at ?? '—'}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={`/reviewer/completeness/applications/${row.id}`}
                                                        >
                                                            Review
                                                        </Link>
                                                    </Button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </CardContent>
                    </Card>
                )}
            </div>
        </>
    );
}
