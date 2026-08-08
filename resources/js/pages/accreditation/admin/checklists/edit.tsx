import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import checklistVersions from '@/routes/admin/checklists';
import type {BreadcrumbItem} from '@/types';

interface EnumOption {
    value: string;
    label: string;
}

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

interface Enums {
    response_types: EnumOption[];
    severities: EnumOption[];
    program_types: EnumOption[];
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
    can_edit: boolean;
    can_publish: boolean;
    can_retire: boolean;
    can_clone: boolean;
    can_delete: boolean;
    can_preview: boolean;
    sections: Section[];
    enums: Enums;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Checklist Administration', href: '/admin/checklists' },
    { title: 'Edit Version', href: '#' },
];

export default function ChecklistEdit({
    id,
    version_code,
    name,
    description,
    program_type,
    status,
    status_label,
    is_editable,
    effective_from,
    effective_until,
    can_publish,
    can_retire,
    can_clone,
    can_delete,
    can_preview,
    sections,
    enums,
}: VersionProps) {
    const meta = useForm({
        version_code,
        name,
        description: description ?? '',
        program_type,
        effective_from: effective_from ?? '',
        effective_until: effective_until ?? '',
    });

    const [sectionForm, setSectionForm] = useState({ code: '', title: '', description: '' });
    const [reqForm, setReqForm] = useState({
        section_id: sections[0]?.id ?? 0,
        code: '',
        requirement: '',
        guidance: '',
        response_type: enums.response_types[0]?.value ?? 'text',
        severity: enums.severities[0]?.value ?? 'minor',
        applicable_program_types: [] as string[],
        is_mandatory: false,
        requires_document: false,
        is_reviewer_only: false,
    });

    const saveMeta = (e: React.FormEvent) => {
        e.preventDefault();
        meta.patch(checklistVersions.update({ checklist_version: id }).url);
    };

    const addSection = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            checklistVersions.sections.store({ checklist_version: id }).url,
            sectionForm,
            { preserveScroll: true, onSuccess: () => setSectionForm({ code: '', title: '', description: '' }) },
        );
    };

    const addRequirement = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(
            checklistVersions.requirements.store({ checklist_version: id }).url,
            reqForm,
            { preserveScroll: true, onSuccess: () => setReqForm({ ...reqForm, code: '', requirement: '', guidance: '' }) },
        );
    };

    const toggleSection = (sectionId: number, active: boolean) =>
        router.post(
            checklistVersions.sections.toggle({ checklist_version: id, section: sectionId }).url,
            { is_active: active },
            { preserveScroll: true },
        );

    const toggleRequirement = (reqId: number, active: boolean) =>
        router.post(
            checklistVersions.requirements.toggle({ checklist_version: id, requirement: reqId }).url,
            { is_active: active },
            { preserveScroll: true },
        );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${version_code}`} />
            <div className="space-y-6 p-6">
                <div className="flex items-start justify-between">
                    <div>
                        <div className="flex items-center gap-2">
                            <h1 className="text-2xl font-semibold">{version_code}</h1>
                            <Badge
                                variant={
                                    status === 'published' ? 'default' : status === 'retired' ? 'secondary' : 'outline'
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
                                <Link href={checklistVersions.preview({ checklist_version: id }).url}>Preview</Link>
                            </Button>
                        )}
                        {can_clone && (
                            <Button variant="outline" asChild>
                                <Link href={checklistVersions.clone({ checklist_version: id }).url}>Clone</Link>
                            </Button>
                        )}
                        {can_publish && (
                            <Button asChild>
                                <Link href={`/admin/checklists/${id}/publish`}>Publish</Link>
                            </Button>
                        )}
                        {can_retire && (
                            <Button variant="destructive" asChild>
                                <Link href={`/admin/checklists/${id}/retire`}>Retire</Link>
                            </Button>
                        )}
                        {can_delete && (
                            <Button variant="destructive" asChild>
                                <Link href={checklistVersions.destroy({ checklist_version: id }).url} method="delete" as="button">
                                    Delete
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                {!is_editable && (
                    <Card className="border-amber-300 bg-amber-50">
                        <CardContent className="p-4 text-sm">
                            This version is <strong>{status_label}</strong> and cannot be edited. Use
                            <strong> Clone</strong> to create a new DRAFT based on it.
                        </CardContent>
                    </Card>
                )}

                {is_editable && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Version metadata</CardTitle>
                            <CardDescription>Editable while the version is a DRAFT.</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={saveMeta} className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-2">
                                    <Label>Version code</Label>
                                    <Input value={meta.data.version_code} onChange={(e) => meta.setData('version_code', e.target.value)} />
                                    <InputError message={meta.errors.version_code} />
                                </div>
                                <div className="space-y-2">
                                    <Label>Name</Label>
                                    <Input value={meta.data.name} onChange={(e) => meta.setData('name', e.target.value)} />
                                    <InputError message={meta.errors.name} />
                                </div>
                                <div className="space-y-2 md:col-span-2">
                                    <Label>Description</Label>
                                    <Textarea
                                        value={meta.data.description}
                                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => meta.setData('description', e.target.value)}
                                    />
                                </div>
                                <div className="space-y-2">
                                    <Label>Program type</Label>
                                    <Select
                                        value={meta.data.program_type}
                                        onValueChange={(v) => meta.setData('program_type', v)}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {enums.program_types.map((p) => (
                                                <SelectItem key={p.value} value={p.value}>
                                                    {p.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={meta.errors.program_type} />
                                </div>
                                <div className="flex items-end">
                                    <Button type="submit" disabled={meta.processing}>
                                        Save metadata
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* Sections + requirements */}
                <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle>Sections</CardTitle>
                            <CardDescription>Add, rename, and organize sections.</CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {sections.map((s) => (
                                <div key={s.id} className="rounded border p-3">
                                    <div className="flex items-center justify-between">
                                        <div>
                                            <span className="font-medium">{s.title}</span>{' '}
                                            <span className="text-xs text-muted-foreground">{s.code}</span>
                                            {!s.is_active && <Badge variant="secondary">Inactive</Badge>}
                                        </div>
                                        {is_editable && (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => toggleSection(s.id, !s.is_active)}
                                            >
                                                {s.is_active ? 'Deactivate' : 'Activate'}
                                            </Button>
                                        )}
                                    </div>
                                    {s.description && (
                                        <p className="mt-1 text-sm text-muted-foreground">{s.description}</p>
                                    )}
                                    <div className="mt-2 space-y-1 text-sm">
                                        {s.requirements.map((r) => (
                                            <div key={r.id} className="flex items-center justify-between rounded bg-muted/40 px-2 py-1">
                                                <span className={r.is_active ? '' : 'line-through opacity-60'}>
                                                    {r.code} — {r.requirement}
                                                </span>
                                                {is_editable && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => toggleRequirement(r.id, !r.is_active)}
                                                    >
                                                        {r.is_active ? 'Off' : 'On'}
                                                    </Button>
                                                )}
                                            </div>
                                        ))}
                                        {s.requirements.length === 0 && (
                                            <p className="text-xs text-muted-foreground">No requirements yet.</p>
                                        )}
                                    </div>
                                </div>
                            ))}

                            {is_editable && (
                                <form onSubmit={addSection} className="space-y-2 border-t pt-3">
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            placeholder="Section code"
                                            value={sectionForm.code}
                                            onChange={(e) => setSectionForm({ ...sectionForm, code: e.target.value })}
                                        />
                                        <Input
                                            placeholder="Title"
                                            value={sectionForm.title}
                                            onChange={(e) => setSectionForm({ ...sectionForm, title: e.target.value })}
                                        />
                                    </div>
                                    <Input
                                        placeholder="Description (optional)"
                                        value={sectionForm.description}
                                        onChange={(e) => setSectionForm({ ...sectionForm, description: e.target.value })}
                                    />
                                    <Button type="submit" size="sm">
                                        Add section
                                    </Button>
                                </form>
                            )}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Add requirement</CardTitle>
                            <CardDescription>
                                Requirements belong to a section and are answered by applicants.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            {is_editable ? (
                                <form onSubmit={addRequirement} className="space-y-3">
                                    <div className="grid grid-cols-2 gap-2">
                                        <Input
                                            placeholder="Requirement code"
                                            value={reqForm.code}
                                            onChange={(e) => setReqForm({ ...reqForm, code: e.target.value })}
                                        />
                                        <Select
                                            value={String(reqForm.section_id)}
                                            onValueChange={(v) => setReqForm({ ...reqForm, section_id: Number(v) })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Section" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {sections.map((s) => (
                                                    <SelectItem key={s.id} value={String(s.id)}>
                                                        {s.title}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <Textarea
                                        placeholder="Requirement text"
                                        value={reqForm.requirement}
                                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setReqForm({ ...reqForm, requirement: e.target.value })}
                                    />
                                    <Textarea
                                        placeholder="Guidance (optional)"
                                        value={reqForm.guidance}
                                        onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setReqForm({ ...reqForm, guidance: e.target.value })}
                                    />
                                    <div className="grid grid-cols-2 gap-2">
                                        <Select
                                            value={reqForm.response_type}
                                            onValueChange={(v) => setReqForm({ ...reqForm, response_type: v })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Response type" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {enums.response_types.map((t) => (
                                                    <SelectItem key={t.value} value={t.value}>
                                                        {t.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                        <Select
                                            value={reqForm.severity}
                                            onValueChange={(v) => setReqForm({ ...reqForm, severity: v })}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Severity" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {enums.severities.map((s) => (
                                                    <SelectItem key={s.value} value={s.value}>
                                                        {s.label}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div className="flex flex-wrap gap-4">
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={reqForm.is_mandatory}
                                                onCheckedChange={(v) => setReqForm({ ...reqForm, is_mandatory: !!v })}
                                            />
                                            Mandatory
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={reqForm.requires_document}
                                                onCheckedChange={(v) => setReqForm({ ...reqForm, requires_document: !!v })}
                                            />
                                            Requires document
                                        </label>
                                        <label className="flex items-center gap-2 text-sm">
                                            <Checkbox
                                                checked={reqForm.is_reviewer_only}
                                                onCheckedChange={(v) => setReqForm({ ...reqForm, is_reviewer_only: !!v })}
                                            />
                                            Reviewer only
                                        </label>
                                    </div>
                                    <div>
                                        <Label className="text-xs">Applicability</Label>
                                        <div className="mt-1 flex flex-wrap gap-2">
                                            {enums.program_types.map((p) => {
                                                const selected = reqForm.applicable_program_types.includes(p.value);

                                                return (
                                                    <button
                                                        type="button"
                                                        key={p.value}
                                                        onClick={() =>
                                                            setReqForm({
                                                                ...reqForm,
                                                                applicable_program_types: selected
                                                                    ? reqForm.applicable_program_types.filter((x) => x !== p.value)
                                                                    : [...reqForm.applicable_program_types, p.value],
                                                            })
                                                        }
                                                        className={`rounded border px-2 py-1 text-xs ${
                                                            selected ? 'bg-primary text-primary-foreground' : 'bg-muted'
                                                        }`}
                                                    >
                                                        {p.label}
                                                    </button>
                                                );
                                            })}
                                        </div>
                                        <p className="mt-1 text-xs text-muted-foreground">
                                            No selection means applicable to all program types.
                                        </p>
                                    </div>
                                    <Button type="submit" size="sm">
                                        Add requirement
                                    </Button>
                                </form>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    This version is read-only. Clone it to make changes.
                                </p>
                            )}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
