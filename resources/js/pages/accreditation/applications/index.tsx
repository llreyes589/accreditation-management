import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface ApplicationRow {
    id: number;
    reference: string;
    institution: string | null;
    program: string | null;
    program_type: string | null;
    application_type: string;
    status: string;
    status_label: string;
    current_step: string;
    current_step_position: number;
    completion_percentage: number;
    updated_at: string | null;
    submitted_at: string | null;
    can_edit: boolean;
    can_withdraw: boolean;
}

function statusVariant(status: string): 'default' | 'secondary' | 'outline' {
    if (status === 'submitted') {
        return 'default';
    }

    if (status === 'draft') {
        return 'secondary';
    }

    return 'outline';
}

export default function ApplicationsIndex({
    applications,
    can_create: canCreate,
}: {
    applications: ApplicationRow[];
    can_create: boolean;
}) {
    const withdraw = (row: ApplicationRow) => {
        if (
            !window.confirm(
                `Withdraw draft ${row.reference}? This cannot be undone.`,
            )
        ) {
            return;
        }

        router.post(
            `/applications/${row.id}/withdraw`,
            {},
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Accreditation Applications" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            Accreditation Applications
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Track and resume accreditation applications for your
                            institution.
                        </p>
                    </div>
                    {canCreate && (
                        <Button asChild>
                            <Link href="/applications/create">
                                Start new application
                            </Link>
                        </Button>
                    )}
                </div>

                {applications.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No applications yet</CardTitle>
                            <CardDescription>
                                {canCreate
                                    ? 'Start a new accreditation application to begin.'
                                    : 'No applications are visible to your role.'}
                            </CardDescription>
                        </CardHeader>
                    </Card>
                ) : (
                    <Card>
                        <CardContent className="p-0">
                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <caption className="sr-only">
                                        List of accreditation applications
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
                                                Type
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
                                                Step
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                Progress
                                            </th>
                                            <th
                                                scope="col"
                                                className="p-3 text-left font-medium"
                                            >
                                                Updated
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
                                                    {row.reference}
                                                </td>
                                                <td className="p-3">
                                                    {row.institution ?? '—'}
                                                </td>
                                                <td className="p-3">
                                                    {row.program ?? '—'}
                                                    {row.program_type && (
                                                        <span className="block text-xs text-muted-foreground">
                                                            {row.program_type}
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="p-3">
                                                    {row.application_type}
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
                                                <td className="p-3 text-xs">
                                                    {row.current_step_position}.{' '}
                                                    {row.current_step}
                                                </td>
                                                <td className="p-3">
                                                    <div className="flex items-center gap-2">
                                                        <div
                                                            className="h-2 w-20 overflow-hidden rounded-full bg-muted"
                                                            role="progressbar"
                                                            aria-valuenow={
                                                                row.completion_percentage
                                                            }
                                                            aria-valuemin={0}
                                                            aria-valuemax={100}
                                                            aria-label={`${row.reference} completion`}
                                                        >
                                                            <div
                                                                className="h-full bg-primary"
                                                                style={{
                                                                    width: `${row.completion_percentage}%`,
                                                                }}
                                                            />
                                                        </div>
                                                        <span className="text-xs tabular-nums">
                                                            {
                                                                row.completion_percentage
                                                            }
                                                            %
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className="p-3 text-xs text-muted-foreground">
                                                    {row.updated_at ?? '—'}
                                                </td>
                                                <td className="p-3 text-xs text-muted-foreground">
                                                    {row.submitted_at ?? '—'}
                                                </td>
                                                <td className="p-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                            variant="outline"
                                                        >
                                                            <Link
                                                                href={`/applications/${row.id}/wizard`}
                                                            >
                                                                {row.can_edit
                                                                    ? 'Resume'
                                                                    : 'View'}
                                                            </Link>
                                                        </Button>
                                                        {row.can_withdraw && (
                                                            <Button
                                                                size="sm"
                                                                variant="ghost"
                                                                onClick={() =>
                                                                    withdraw(
                                                                        row,
                                                                    )
                                                                }
                                                            >
                                                                Withdraw
                                                            </Button>
                                                        )}
                                                    </div>
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
