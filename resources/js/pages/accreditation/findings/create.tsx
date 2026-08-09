import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import findings from '@/routes/findings';
import inspections from '@/routes/inspections';
import type { BreadcrumbItem } from '@/types';

interface CriterionInfo {
    id: number;
    section_name: string;
    requirement_code: string;
    title: string;
    description: string;
    severity: string;
    result: string | null;
    inspector_notes: string | null;
}

interface Props {
    inspection_id: number;
    criterion: CriterionInfo | null;
    categories: Record<string, string>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Findings', href: findings.index().url },
    { title: 'New', href: '#' },
];

export default function FindingCreate({
    inspection_id,
    criterion,
    categories,
}: Props) {
    const { data, setData, post, processing, errors } = useForm({
        inspection_id: inspection_id,
        inspection_criterion_id: criterion?.id ?? '',
        category: '' as string,
        title: '' as string,
        description: '' as string,
        severity: criterion?.severity ?? 'unspecified',
        due_date: '' as string,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(findings.store().url);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Finding" />

            <div className="flex flex-col gap-6 p-6">
                <div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Raise a finding
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {criterion
                            ? 'From inspection criterion.'
                            : 'General finding for this inspection.'}
                    </p>
                </div>

                {criterion && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Source criterion</CardTitle>
                            <CardDescription>
                                {criterion.section_name} ·{' '}
                                {criterion.requirement_code}
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-2 text-sm">
                            <div>
                                <span className="font-medium">
                                    Requirement:{' '}
                                </span>
                                {criterion.title}
                            </div>
                            <div>
                                <span className="font-medium">
                                    Severity at inspection:{' '}
                                </span>
                                {criterion.severity}
                            </div>
                            <div>
                                <span className="font-medium">Result: </span>
                                {criterion.result ?? '—'}
                            </div>
                            {criterion.inspector_notes && (
                                <div>
                                    <span className="font-medium">
                                        Inspector notes:{' '}
                                    </span>
                                    {criterion.inspector_notes}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Finding details</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="flex flex-col gap-4">
                            <div className="flex flex-col gap-2">
                                <Label htmlFor="category">Classification</Label>
                                <select
                                    id="category"
                                    className="rounded-md border bg-background px-3 py-2 text-sm"
                                    value={data.category}
                                    onChange={(e) =>
                                        setData('category', e.target.value)
                                    }
                                >
                                    <option value="">Select…</option>
                                    {Object.entries(categories).map(
                                        ([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ),
                                    )}
                                </select>
                                {errors.category && (
                                    <p className="text-xs text-destructive">
                                        {errors.category}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={data.title}
                                    onChange={(e) =>
                                        setData('title', e.target.value)
                                    }
                                />
                                {errors.title && (
                                    <p className="text-xs text-destructive">
                                        {errors.title}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={data.description}
                                    onChange={(e) =>
                                        setData('description', e.target.value)
                                    }
                                />
                                {errors.description && (
                                    <p className="text-xs text-destructive">
                                        {errors.description}
                                    </p>
                                )}
                            </div>

                            <div className="flex flex-col gap-2">
                                <Label htmlFor="due_date">
                                    Due date (optional)
                                </Label>
                                <Input
                                    id="due_date"
                                    type="date"
                                    value={data.due_date}
                                    onChange={(e) =>
                                        setData('due_date', e.target.value)
                                    }
                                />
                                {errors.due_date && (
                                    <p className="text-xs text-destructive">
                                        {errors.due_date}
                                    </p>
                                )}
                            </div>

                            <div className="flex items-center gap-2">
                                <Button type="submit" disabled={processing}>
                                    Create finding
                                </Button>
                                <Link
                                    href={
                                        inspections.show({
                                            inspection: inspection_id,
                                        }).url
                                    }
                                >
                                    <Button variant="outline" type="button">
                                        Cancel
                                    </Button>
                                </Link>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
