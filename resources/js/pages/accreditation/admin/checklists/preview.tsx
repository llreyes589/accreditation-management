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

interface PreviewProps {
    id: number;
    version_code: string;
    name: string;
    program_type_label: string;
    status_label: string;
    sections: Section[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Checklist Administration', href: '/admin/checklists' },
    { title: 'Applicant Preview', href: '#' },
];

export default function ChecklistPreview({
    id,
    version_code,
    name,
    program_type_label,
    status_label,
    sections,
}: PreviewProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Preview ${version_code}`} />
            <div className="max-w-3xl space-y-6 p-6">
                <div className="flex items-center justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-semibold">{name}</h1>
                            <Badge variant="outline">PREVIEW</Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {version_code} · {program_type_label} ·{' '}
                            {status_label}
                        </p>
                    </div>
                    <Button variant="outline" asChild>
                        <Link
                            href={
                                checklistVersions.show({
                                    checklist_version: id,
                                }).url
                            }
                        >
                            Back to version
                        </Link>
                    </Button>
                </div>

                <p className="rounded bg-amber-50 p-3 text-sm text-amber-800">
                    This is a read-only preview of how applicants will see this
                    checklist. It does not create or modify any application.
                </p>

                {sections.map((s) => (
                    <Card key={s.id}>
                        <CardHeader>
                            <CardTitle className="text-lg">{s.title}</CardTitle>
                            {s.description && (
                                <CardDescription>
                                    {s.description}
                                </CardDescription>
                            )}
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {s.requirements.map((r) => (
                                <div key={r.id} className="rounded border p-3">
                                    <div className="flex items-start justify-between gap-2">
                                        <div>
                                            <div className="font-medium">
                                                {r.requirement}
                                                {r.is_mandatory && (
                                                    <span className="ml-1 text-red-600">
                                                        *
                                                    </span>
                                                )}
                                            </div>
                                            {r.guidance && (
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {r.guidance}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 flex-col items-end gap-1 text-xs">
                                            <Badge
                                                variant={
                                                    r.is_mandatory
                                                        ? 'destructive'
                                                        : 'secondary'
                                                }
                                            >
                                                {r.is_mandatory
                                                    ? 'Required'
                                                    : 'Optional'}
                                            </Badge>
                                            <span className="text-muted-foreground uppercase">
                                                {r.response_type}
                                            </span>
                                            {r.requires_document && (
                                                <span className="text-muted-foreground">
                                                    Document required
                                                </span>
                                            )}
                                            {r.is_reviewer_only && (
                                                <span className="text-muted-foreground">
                                                    Reviewer only
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ))}
            </div>
        </AppLayout>
    );
}
