import { Head, Link, router, useForm } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import checklistVersions from '@/routes/admin/checklists';
import type {BreadcrumbItem} from '@/types';

interface VersionRow {
    id: number;
    version_code: string;
    name: string;
    program_type: string;
    program_type_label: string;
    status: string;
    status_label: string;
    effective_from: string | null;
    effective_until: string | null;
    section_count: number;
    active_requirement_count: number;
    created_by: string | null;
    published_at: string | null;
    retired_at: string | null;
}

interface Option {
    value: string;
    label: string;
}

interface IndexProps {
    versions: VersionRow[];
    filters: { status?: string; program_type?: string; version_code?: string };
    statuses: Option[];
    program_types: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Checklist Administration', href: '/admin/checklists' },
];

export default function ChecklistIndex({ versions, filters, statuses, program_types }: IndexProps) {
    const { data, setData } = useForm({
        status: filters.status ?? '',
        program_type: filters.program_type ?? '',
        version_code: filters.version_code ?? '',
    });

    const applyFilters = () => {
        const params: Record<string, string> = {};

        if (data.status) {
params.status = data.status;
}

        if (data.program_type) {
params.program_type = data.program_type;
}

        if (data.version_code) {
params.version_code = data.version_code;
}

        router.get(checklistVersions.index().url, params, { preserveState: true, replace: true });
    };

    const clearFilters = () => {
        setData({ status: '', program_type: '', version_code: '' });
        router.get(checklistVersions.index().url, {}, { replace: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Checklist Administration" />
            <div className="space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Checklist Administration</h1>
                        <p className="text-sm text-muted-foreground">
                            Manage the configurable accreditation checklist versions.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={checklistVersions.create().url}>New Version</Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Filters</CardTitle>
                        <CardDescription>Narrow the version list by status, program type, or code.</CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-wrap items-end gap-4">
                        <div className="space-y-2">
                            <Label htmlFor="version_code">Version code</Label>
                            <Input
                                id="version_code"
                                value={data.version_code}
                                onChange={(e) => setData('version_code', e.target.value)}
                                placeholder="e.g. 2026.1"
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>Status</Label>
                            <Select value={data.status} onValueChange={(v) => setData('status', v)}>
                                <SelectTrigger className="w-40">
                                    <SelectValue placeholder="Any" />
                                </SelectTrigger>
                                <SelectContent>
                                    {statuses.map((s) => (
                                        <SelectItem key={s.value} value={s.value}>
                                            {s.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-2">
                            <Label>Program type</Label>
                            <Select value={data.program_type} onValueChange={(v) => setData('program_type', v)}>
                                <SelectTrigger className="w-56">
                                    <SelectValue placeholder="Any" />
                                </SelectTrigger>
                                <SelectContent>
                                    {program_types.map((p) => (
                                        <SelectItem key={p.value} value={p.value}>
                                            {p.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <Button onClick={applyFilters}>Apply</Button>
                        <Button variant="outline" onClick={clearFilters}>
                            Clear
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="p-0">
                        <table className="w-full text-sm">
                            <thead className="border-b bg-muted/50">
                                <tr className="text-left">
                                    <th className="p-3">Version</th>
                                    <th className="p-3">Program</th>
                                    <th className="p-3">Status</th>
                                    <th className="p-3">Effective</th>
                                    <th className="p-3 text-right">Sections</th>
                                    <th className="p-3 text-right">Active Reqs</th>
                                    <th className="p-3">Created By</th>
                                    <th className="p-3">Published</th>
                                    <th className="p-3">Retired</th>
                                    <th className="p-3" />
                                </tr>
                            </thead>
                            <tbody>
                                {versions.length === 0 && (
                                    <tr>
                                        <td colSpan={10} className="p-6 text-center text-muted-foreground">
                                            No checklist versions match the current filters.
                                        </td>
                                    </tr>
                                )}
                                {versions.map((v) => (
                                    <tr key={v.id} className="border-b">
                                        <td className="p-3">
                                            <div className="font-medium">{v.version_code}</div>
                                            <div className="text-muted-foreground">{v.name}</div>
                                        </td>
                                        <td className="p-3">{v.program_type_label}</td>
                                        <td className="p-3">
                                            <Badge
                                                variant={
                                                    v.status === 'published'
                                                        ? 'default'
                                                        : v.status === 'retired'
                                                          ? 'secondary'
                                                          : 'outline'
                                                }
                                            >
                                                {v.status_label}
                                            </Badge>
                                        </td>
                                        <td className="p-3 text-xs text-muted-foreground">
                                            {v.effective_from ?? '—'}
                                            {v.effective_until ? ` → ${v.effective_until}` : ''}
                                        </td>
                                        <td className="p-3 text-right">{v.section_count}</td>
                                        <td className="p-3 text-right">{v.active_requirement_count}</td>
                                        <td className="p-3">{v.created_by ?? '—'}</td>
                                        <td className="p-3 text-xs text-muted-foreground">{v.published_at ?? '—'}</td>
                                        <td className="p-3 text-xs text-muted-foreground">{v.retired_at ?? '—'}</td>
                                        <td className="p-3 text-right">
                                            <Button variant="link" asChild>
                                                <Link href={checklistVersions.show({ checklist_version: v.id }).url}>
                                                    View
                                                </Link>
                                            </Button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
