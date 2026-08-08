import { Head, Link, useForm } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import checklistVersions from '@/routes/admin/checklists';
import type {BreadcrumbItem} from '@/types';

interface PublishProps {
    id: number;
    version_code: string;
    name: string;
    program_type_label: string;
    section_count: number;
    active_requirement_count: number;
    errors: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Checklist Administration', href: '/admin/checklists' },
    { title: 'Publish', href: '#' },
];

export default function ChecklistPublish({
    id,
    version_code,
    name,
    program_type_label,
    section_count,
    active_requirement_count,
    errors,
}: PublishProps) {
    const { post, processing } = useForm({});

    const publish = () => post(checklistVersions.publish({ checklist_version: id }).url);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Publish ${version_code}`} />
            <div className="max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Publish {version_code}</h1>
                    <p className="text-sm text-muted-foreground">
                        {name} · {program_type_label}
                    </p>
                </div>

                {errors.length > 0 && (
                    <Alert variant="destructive">
                        <AlertTitle>Cannot publish yet</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-5">
                                {errors.map((e) => (
                                    <li key={e}>{e}</li>
                                ))}
                            </ul>
                        </AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Publishing summary</CardTitle>
                        <CardDescription>
                            Confirming will make this version the active checklist for new
                            applications.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <div className="text-xs text-muted-foreground">Sections</div>
                                <div className="text-lg font-medium">{section_count}</div>
                            </div>
                            <div>
                                <div className="text-xs text-muted-foreground">Active requirements</div>
                                <div className="text-lg font-medium">{active_requirement_count}</div>
                            </div>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            Publishing is recorded in the audit log and cannot be undone — a published
                            version is immutable. To make changes later, clone this version.
                        </p>
                    </CardContent>
                </Card>

                <div className="flex justify-end gap-2">
                    <Button variant="outline" asChild>
                        <Link href={checklistVersions.show({ checklist_version: id }).url}>Cancel</Link>
                    </Button>
                    <Button onClick={publish} disabled={processing || errors.length > 0}>
                        Confirm &amp; publish
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
