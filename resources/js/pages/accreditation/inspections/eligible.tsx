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

interface EligibleApplicationRow {
    id: number;
    application_reference: string;
    institution: string | null;
    program: string | null;
    program_type: string | null;
    application_type: string | null;
    completed_for_inspection_at: string | null;
    checklist_version: string | null;
    current_inspection_status: string | null;
    existing_inspection_at: string | null;
}

function statusVariant(
    status: string | null,
): 'default' | 'secondary' | 'destructive' | 'outline' {
    if (status === 'scheduled' || status === 'in_progress') {
        return 'default';
    }

    if (status === 'completed') {
        return 'secondary';
    }

    if (status === 'cancelled') {
        return 'destructive';
    }

    return 'outline';
}

export default function InspectionEligible({
    applications,
}: {
    applications: EligibleApplicationRow[];
}) {
    return (
        <>
            <Head title="Schedule Inspection" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Schedule Inspection
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Applications marked complete for inspection. This is an
                        administrative and documentary inspection only — it does
                        not determine compliance or accreditation eligibility.
                    </p>
                </div>

                {applications.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>Nothing to schedule</CardTitle>
                            <CardDescription>
                                No applications are currently complete for
                                inspection.
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <caption className="sr-only">
                                        Applications eligible for inspection
                                    </caption>
                                    <thead className="border-b bg-muted/50">
                                        <tr>
                                            <th className="p-3 text-left font-medium">
                                                Reference
                                            </th>
                                            <th className="p-3 text-left font-medium">
                                                Institution
                                            </th>
                                            <th className="p-3 text-left font-medium">
                                                Program
                                            </th>
                                            <th className="p-3 text-left font-medium">
                                                Type
                                            </th>
                                            <th className="p-3 text-left font-medium">
                                                Checklist
                                            </th>
                                            <th className="p-3 text-left font-medium">
                                                Complete For Inspection
                                            </th>
                                            <th className="p-3 text-left font-medium">
                                                Current Inspection
                                            </th>
                                            <th className="p-3 text-right font-medium">
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
                                                <td className="p-3 text-xs text-muted-foreground">
                                                    {row.application_type ??
                                                        '—'}
                                                </td>
                                                <td className="p-3 text-xs">
                                                    {row.checklist_version ??
                                                        '—'}
                                                </td>
                                                <td className="p-3 text-xs text-muted-foreground">
                                                    {row.completed_for_inspection_at ??
                                                        '—'}
                                                </td>
                                                <td className="p-3">
                                                    {row.current_inspection_status ? (
                                                        <Badge
                                                            variant={statusVariant(
                                                                row.current_inspection_status,
                                                            )}
                                                        >
                                                            {row.current_inspection_status.replace(
                                                                /_/g,
                                                                ' ',
                                                            )}
                                                        </Badge>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            None
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <Button
                                                        asChild
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        <Link
                                                            href={`/inspections/applications/${row.id}/create`}
                                                        >
                                                            Schedule
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
