import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import checklistVersions from '@/routes/admin/checklists';
import type {BreadcrumbItem} from '@/types';

interface Option {
    value: string;
    label: string;
}

interface CreateProps {
    program_types: Option[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Checklist Administration', href: '/admin/checklists' },
    { title: 'New Version', href: '/admin/checklists/create' },
];

export default function ChecklistCreate({ program_types }: CreateProps) {
    const { data, setData, post, errors, processing } = useForm({
        version_code: '',
        name: '',
        description: '',
        program_type: '',
        effective_from: '',
        effective_until: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(checklistVersions.store().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="New Checklist Version" />
            <div className="max-w-2xl space-y-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold">New Checklist Version</h1>
                    <p className="text-sm text-muted-foreground">
                        Create a new DRAFT version. It must be populated with sections and
                        requirements and published before applications can use it.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Version details</CardTitle>
                        <CardDescription>Every field below is required except description.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="version_code">Version code</Label>
                                <Input
                                    id="version_code"
                                    value={data.version_code}
                                    onChange={(e) => setData('version_code', e.target.value)}
                                    placeholder="e.g. 2026.2"
                                />
                                <InputError message={errors.version_code} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="e.g. Combined Pathology Checklist"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="description">Description</Label>
                                <Input
                                    id="description"
                                    value={data.description}
                                    onChange={(e) => setData('description', e.target.value)}
                                />
                                <InputError message={errors.description} />
                            </div>

                            <div className="space-y-2">
                                <Label>Program type</Label>
                                <Select value={data.program_type} onValueChange={(v) => setData('program_type', v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select program type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {program_types.map((p) => (
                                            <SelectItem key={p.value} value={p.value}>
                                                {p.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.program_type} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="effective_from">Effective from</Label>
                                    <Input
                                        id="effective_from"
                                        type="date"
                                        value={data.effective_from}
                                        onChange={(e) => setData('effective_from', e.target.value)}
                                    />
                                    <InputError message={errors.effective_from} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="effective_until">Effective until</Label>
                                    <Input
                                        id="effective_until"
                                        type="date"
                                        value={data.effective_until}
                                        onChange={(e) => setData('effective_until', e.target.value)}
                                    />
                                    <InputError message={errors.effective_until} />
                                </div>
                            </div>

                            <div className="flex justify-end gap-2">
                                <Button variant="outline" asChild>
                                    <Link href={checklistVersions.index().url}>Cancel</Link>
                                </Button>
                                <Button type="submit" disabled={processing}>
                                    Create draft
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
