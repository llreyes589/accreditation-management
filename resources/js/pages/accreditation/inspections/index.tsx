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
import inspections from '@/routes/inspections';
import type {BreadcrumbItem} from '@/types';

interface InspectionRow {
    id: number;
    status: string;
    status_label: string;
    inspection_type: string;
    scheduled_start_at: string | null;
    scheduled_end_at: string | null;
    timezone: string | null;
    location: string | null;
    application_reference: string | null;
    institution: string | null;
    program: string | null;
    can_view: boolean;
    lead_inspector: string | null;
}

interface Props {
    inspections: InspectionRow[];
    can_create: boolean;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Inspections', href: inspections.index().url },
];

export default function InspectionIndex({ inspections, can_create }: Props) {
    const [search, setSearch] = useState('');

    const filtered = inspections.filter((row) => {
        const haystack = [
            row.application_reference,
            row.institution,
            row.program,
            row.status_label,
            row.lead_inspector,
        ]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();

        return haystack.includes(search.toLowerCase());
    });

    const statusVariant = (status: string) => {
        switch (status) {
            case 'scheduled':
            case 'rescheduled':
                return 'default';
            case 'in_progress':
                return 'secondary';
            case 'submitted_for_review':
                return 'outline';
            case 'completed':
                return 'default';
            case 'cancelled':
                return 'destructive';
            default:
                return 'outline';
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Inspections" />

            <div className="flex flex-col gap-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold tracking-tight">Inspections</h1>
                        <p className="text-sm text-muted-foreground">
                            Scheduled and in-progress inspection checklists.
                        </p>
                    </div>
                    {can_create && (
                        <Link href={inspections.eligible().url}>
                            <Button>Schedule inspection</Button>
                        </Link>
                    )}
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Inspection list</CardTitle>
                        <CardDescription>
                            {filtered.length} inspection
                            {filtered.length === 1 ? '' : 's'} shown.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <Input
                            placeholder="Search by reference, institution, program, or status"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                        />

                        {filtered.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                No inspections match your search.
                            </p>
                        ) : (
                            <div className="overflow-x-auto rounded-md border">
                                <table className="w-full text-sm">
                                    <thead className="bg-muted/50 text-left text-muted-foreground">
                                        <tr>
                                            <th className="px-4 py-2 font-medium">Reference</th>
                                            <th className="px-4 py-2 font-medium">Institution</th>
                                            <th className="px-4 py-2 font-medium">Program</th>
                                            <th className="px-4 py-2 font-medium">Lead inspector</th>
                                            <th className="px-4 py-2 font-medium">Scheduled</th>
                                            <th className="px-4 py-2 font-medium">Status</th>
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
                                                    {row.application_reference ?? `#${row.id}`}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {row.institution ?? '—'}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {row.program ?? '—'}
                                                </td>
                                                <td className="px-4 py-2">
                                                    {row.lead_inspector ?? '—'}
                                                </td>
                                                <td className="px-4 py-2 whitespace-nowrap">
                                                    {row.scheduled_start_at ?? '—'}
                                                </td>
                                                <td className="px-4 py-2">
                                                    <Badge variant={statusVariant(row.status)}>
                                                        {row.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-2 text-right">
                                                    {row.can_view && (
                                                        <Link
                                                            href={inspections.show({
                                                                inspection: row.id,
                                                            }).url}
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
