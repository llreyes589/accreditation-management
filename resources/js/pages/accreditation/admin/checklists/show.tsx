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
import AppLayout from '@/layouts/app-layout';
import checklistVersions from '@/routes/admin/checklists';
import type { BreadcrumbItem } from '@/types';

interface Requirement {
    id: number;
    code: string;
    requirement: string;
    guidance: string | null;
    response_type: string;
    applicable_program_types: string[] | null;
    severity: string;
    severity_label: string;
    is_mandatory: boolean;
    is_reviewer_only: boolean;
    requires_document: boolean;
    position: number;
    is_active: boolean;
}

interface Section {
    id: number;
    code: string;
    title: string;
    description: string | null;
    position: number;
    is_active: boolean;
    requirements: Requirement[];
}

interface VersionProps {
    id: number;
    version_code: string;
    name: string;
    description: string | null;
    program_type: string;
    program_type_label: string;
    status: string;
    status_label: string;
    is_editable: boolean;
    effective_from: string | null;
    effective_until: string | null;
    created_by: string | null;
    published_at: string | null;
    published_by: string | null;
    retired_at: string | null;
    retired_by: string | null;
    can_edit: boolean;
    can_publish: boolean;
    can_retire: boolean;
    can_clone: boolean;
    can_delete: boolean;
    can_preview: boolean;
    sections: Section[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Checklist Administration', href: '/admin/checklists' },
    { title: 'Version Detail', href: '#' },
];

export default function ChecklistShow({
    id,
    version_code,
    name,
    description,
    program_type_label,
    status,
    status_label,
    effective_from,
    effective_until,
    created_by,
    published_at,
    published_by,
    retired_at,
    can_edit,
    can_publish,
    can_retire,
    can_clone,
    can_delete,
    can_preview,
    sections,
}: VersionProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Checklist ${version_code}`} />
            <div className="space-y-6 p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-semibold">
                                {version_code}
                            </h1>
                            <Badge
                                variant={
                                    status === 'published'
                                        ? 'default'
                                        : status === 'retired'
                                          ? 'secondary'
                                          : 'outline'
                                }
                            >
                                {status_label}
                            </Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">{name}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {can_preview && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        checklistVersions.preview({
                                            checklist_version: id,
                                        }).url
                                    }
                                >
                                    Preview
                                </Link>
                            </Button>
                        )}
                        {can_edit && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        checklistVersions.edit({
                                            checklist_version: id,
                                        }).url
                                    }
                                >
                                    Edit
                                </Link>
                            </Button>
                        )}
                        {can_publish && (
                            <Button asChild>
                                <Link href={`/admin/checklists/${id}/publish`}>
                                    Publish
                                </Link>
                            </Button>
                        )}
                        {can_retire && (
                            <Button variant="destructive" asChild>
                                <Link href={`/admin/checklists/${id}/retire`}>
                                    Retire
                                </Link>
                            </Button>
                        )}
                        {can_clone && (
                            <Button variant="outline" asChild>
                                <Link
                                    href={
                                        checklistVersions.clone({
                                            checklist_version: id,
                                        }).url
                                    }
                                >
                                    Clone
                                </Link>
                            </Button>
                        )}
                        {can_delete && (
                            <Button variant="destructive" asChild>
                                <Link
                                    href={
                                        checklistVersions.destroy({
                                            checklist_version: id,
                                        }).url
                                    }
                                    method="delete"
                                    as="button"
                                >
                                    Delete
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <Card>
                    <CardContent className="grid grid-cols-2 gap-4 p-4 text-sm md:grid-cols-3">
                        <Meta label="Program type" value={program_type_label} />
                        <Meta label="Created by" value={created_by ?? '—'} />
                        <Meta
                            label="Effective"
                            value={effectiveRange(
                                effective_from,
                                effective_until,
                            )}
                        />
                        <Meta label="Published" value={published_at ?? '—'} />
                        <Meta
                            label="Published by"
                            value={published_by ?? '—'}
                        />
                        <Meta label="Retired" value={retired_at ?? '—'} />
                        {description && (
                            <Meta label="Description" value={description} />
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Contents</CardTitle>
                        <CardDescription>
                            Read-only view of this version. {sections.length}{' '}
                            section(s).
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-6">
                        {sections.map((s) => (
                            <div key={s.id}>
                                <div className="mb-2 flex items-center gap-2">
                                    <h3 className="font-medium">{s.title}</h3>
                                    <span className="text-xs text-muted-foreground">
                                        {s.code}
                                    </span>
                                    {!s.is_active && (
                                        <Badge variant="secondary">
                                            Inactive
                                        </Badge>
                                    )}
                                </div>
                                {s.description && (
                                    <p className="mb-2 text-sm text-muted-foreground">
                                        {s.description}
                                    </p>
                                )}
                                <table className="w-full text-sm">
                                    <thead className="border-b">
                                        <tr className="text-left text-xs text-muted-foreground">
                                            <th className="p-2">Code</th>
                                            <th className="p-2">Requirement</th>
                                            <th className="p-2">Severity</th>
                                            <th className="p-2">Mandatory</th>
                                            <th className="p-2">Response</th>
                                            <th className="p-2">Doc</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {s.requirements.map((r) => (
                                            <tr key={r.id} className="border-b">
                                                <td className="p-2 font-mono text-xs">
                                                    {r.code}
                                                </td>
                                                <td className="p-2">
                                                    {r.requirement}
                                                </td>
                                                <td className="p-2">
                                                    {r.severity_label}
                                                </td>
                                                <td className="p-2">
                                                    {r.is_mandatory
                                                        ? 'Yes'
                                                        : 'No'}
                                                </td>
                                                <td className="p-2 uppercase">
                                                    {r.response_type}
                                                </td>
                                                <td className="p-2">
                                                    {r.requires_document
                                                        ? 'Yes'
                                                        : 'No'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ))}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function Meta({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <div className="text-xs text-muted-foreground">{label}</div>
            <div className="font-medium">{value}</div>
        </div>
    );
}

function effectiveRange(from: string | null, until: string | null): string {
    if (!from && !until) {
        return '—';
    }

    return `${from ?? '—'} → ${until ?? 'open'}`;
}
