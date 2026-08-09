import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
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
import AppLayout from '@/layouts/app-layout';
import findingsRoute from '@/routes/findings';
import inspectionsRoute from '@/routes/inspections';
import type { BreadcrumbItem } from '@/types';

interface FindingRow {
    id: number;
    finding_reference: string;
    title: string;
    category: string;
    severity: string;
    status: string;
    status_label: string;
    due_date: string | null;
    is_overdue: boolean;
    application_reference: string | null;
    institution: string | null;
    corrective_action_count: number;
    can_view: boolean;
}

interface Props {
    findings: FindingRow[];
    can_create: boolean;
    categories: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Findings', href: findingsRoute.index().url },
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
        case 'voided':
        case 'waived':
        case 'reopened':
            return 'destructive';
        default:
            return 'outline';
    }
};

export default function FindingIndex({ findings, can_create }: Props) {
    const [search, setSearch] = useState('');

    const filtered = findings.filter((row) => {
        const haystack = [
            row.finding_reference,
            row.title,
            row.application_reference,
            row.institution,
            row.status_label,
            row.category,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(search.toLowerCase());
    });

    const overdueCount = findings.filter((f) => f.is_overdue).length;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Findings" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">
                            Findings & Corrective Actions
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Traceable findings raised from completed
                            inspections.
                            {overdueCount > 0 && (
                                <span className="ml-2 font-medium text-destructive">
                                    {overdueCount} overdue
                                </span>
                            )}
                        </p>
                    </div>
                    {can_create && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={inspectionsRoute.index().url}>
                                Raise finding
                            </Link>
                        </Button>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Findings</CardTitle>
                        <CardDescription>
                            {filtered.length} finding
                            {filtered.length === 1 ? '' : 's'} shown.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <Input
                            placeholder="Search by reference, title, application, institution, or status"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />

                        {filtered.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No findings match your search.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">
                                                Reference
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Title
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Institution
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Category
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Due
                                            </th>
                                            <th className="px-4 py-2 font-medium">
                                                Status
                                            </th>
                                            <th className="px-4 py-2" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {filtered.map((row) => (
                                            <tr
                                                key={row.id}
                                                className="border-t"
                                            >
                                                <td className="px-4 py-2 font-medium">
                                                    {row.finding_reference}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {row.title}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {row.institution ?? '—'}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <span className="capitalize">
                                                        {row.category.replace(
                                                            /_/g,
                                                            ' ',
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-2 whitespace-nowrap">
                                                    {row.due_date ? (
                                                        <span
                                                            className={
                                                                row.is_overdue
                                                                    ? 'font-medium text-destructive'
                                                                    : ''
                                                            }
                                                        >
                                                            {row.due_date}
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Badge
                                                        variant={statusVariant(
                                                            row.status,
                                                        )}
                                                    >
                                                        {row.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    {row.can_view && (
                                                        <Link
                                                            href={
                                                                findingsRoute.show(
                                                                    {
                                                                        finding:
                                                                            row.id,
                                                                    },
                                                                ).url
                                                            }
                                                        >
                                                            <Button
                                                                variant="outline"
                                                                size="sm"
                                                            >
                                                                View
                                                            </Button>
                                                        </Link>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
