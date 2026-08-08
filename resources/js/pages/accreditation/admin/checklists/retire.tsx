import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import checklistVersions from '@/routes/admin/checklists';
import type {BreadcrumbItem} from '@/types';

interface RetireProps {
    id: number;
    version_code: string;
    name: string;
    program_type_label: string;
    active_application_count: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Checklist Administration', href: '/admin/checklists' },
    { title: 'Retire', href: '#' },
];

export default function ChecklistRetire({
    id,
    version_code,
    name,
    program_type_label,
    active_application_count,
}: RetireProps) {
    const { data, setData, post, errors, processing } = useForm({
        retirement_reason: '',
    });

    const retire = (e: React.FormEvent) => {
        e.preventDefault();
        post(checklistVersions.retire({ checklist_version: id }).url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Retire ${version_code}`} />
            <div className="max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">Retire {version_code}</h1>
                    <p className="text-sm text-muted-foreground">
                        {name} · {program_type_label}
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Confirm retirement</CardTitle>
                        <CardDescription>
                            Retiring makes this version unavailable for new applications. Existing
                            applications that reference it remain valid and unchanged.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={retire} className="space-y-4">
                            <div className="rounded bg-muted p-3 text-sm">
                                Applications currently using this version:{' '}
                                <strong>{active_application_count}</strong>
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="retirement_reason">Retirement reason (optional)</Label>
                                <Textarea
                                    id="retirement_reason"
                                    value={data.retirement_reason}
                                    onChange={(e) => setData('retirement_reason', e.target.value)}
                                    placeholder="e.g. Superseded by version 2026.2"
                                />
                                <InputError message={errors.retirement_reason} />
                            </div>
                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={checklistVersions.show({ checklist_version: id }).url}>Cancel</Link>
                                </Button>
                                <Button variant="destructive" type="submit" disabled={processing}>
                                    Confirm &amp; retire
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
