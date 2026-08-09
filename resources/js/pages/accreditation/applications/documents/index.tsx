import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
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

interface DocRequirement {
    id: number;
    code: string | null;
    title: string | null;
    is_mandatory: boolean;
    allows_multiple_documents: boolean;
    has_document: boolean;
}

interface Doc {
    id: number;
    status: string;
    status_label: string;
    requirement_code: string | null;
    requirement_title: string | null;
    is_mandatory: boolean;
    filename: string | null;
    uploaded_at: string | null;
    replacement_needed: boolean;
}

interface Props {
    application: { id: number; application_reference: string; status: string };
    documents: Doc[];
    requirements: DocRequirement[];
}

const STATUS_STYLES: Record<string, string> = {
    uploaded: 'bg-blue-100 text-blue-800',
    under_review: 'bg-yellow-100 text-yellow-800',
    accepted: 'bg-green-100 text-green-800',
    rejected: 'bg-red-100 text-red-800',
    replacement_requested: 'bg-orange-100 text-orange-800',
    superseded: 'bg-gray-100 text-gray-800',
    withdrawn: 'bg-gray-100 text-gray-800',
};

export default function DocumentIndex({
    application,
    documents,
    requirements,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        document: File | null;
        application_requirement_id: string;
        comment: string;
    }>({
        document: null,
        application_requirement_id: '',
        comment: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/applications/${application.id}/documents`, {
            forceFormData: true,
            onSuccess: () => reset('document', 'comment'),
        });
    }

    return (
        <AppShell application={application}>
            <div className="space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Upload a supporting document</CardTitle>
                        <CardDescription>
                            Attach a document to a checklist requirement that
                            requires one. Permitted formats: PDF, DOCX, XLSX,
                            JPG, JPEG, PNG (max 20 MB).
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="application_requirement_id">
                                    Requirement
                                </Label>
                                <select
                                    id="application_requirement_id"
                                    value={data.application_requirement_id}
                                    onChange={(e) =>
                                        setData(
                                            'application_requirement_id',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">
                                        Application-level document
                                    </option>
                                    {requirements.map((r) => (
                                        <option
                                            key={r.id}
                                            value={r.id}
                                            disabled={
                                                !r.allows_multiple_documents &&
                                                r.has_document
                                            }
                                        >
                                            {r.code ? `${r.code} — ` : ''}
                                            {r.title}
                                            {r.is_mandatory
                                                ? ' (mandatory)'
                                                : ' (optional)'}
                                            {!r.allows_multiple_documents &&
                                            r.has_document
                                                ? ' — already submitted'
                                                : ''}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="document">File</Label>
                                <Input
                                    id="document"
                                    type="file"
                                    accept=".pdf,.docx,.xlsx,.jpg,.jpeg,.png"
                                    onChange={(e) =>
                                        setData(
                                            'document',
                                            e.target.files?.[0] ?? null,
                                        )
                                    }
                                />
                                <InputError message={errors.document} />
                            </div>
                            <Button
                                type="submit"
                                disabled={processing || !data.document}
                            >
                                Upload document
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Submitted documents</CardTitle>
                        <CardDescription>
                            Status reflects the latest reviewer decision.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {documents.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No documents uploaded yet.
                            </p>
                        ) : (
                            <ul className="divide-y">
                                {documents.map((doc) => (
                                    <li
                                        key={doc.id}
                                        className="flex items-center justify-between py-3"
                                    >
                                        <div>
                                            <p className="text-sm font-medium">
                                                {doc.requirement_code
                                                    ? `${doc.requirement_code} — `
                                                    : ''}
                                                {doc.requirement_title ??
                                                    'Application document'}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {doc.filename}
                                                {doc.uploaded_at
                                                    ? ` · ${doc.uploaded_at}`
                                                    : ''}
                                                {doc.replacement_needed
                                                    ? ' · replacement needed'
                                                    : ''}
                                            </p>
                                        </div>
                                        <div className="flex items-center gap-3">
                                            <span
                                                className={`rounded-full px-2 py-1 text-xs font-medium ${
                                                    STATUS_STYLES[doc.status] ??
                                                    'bg-gray-100 text-gray-800'
                                                }`}
                                            >
                                                {doc.status_label}
                                            </span>
                                            <Link
                                                href={`/applications/${application.id}/documents/${doc.id}`}
                                                className="text-sm text-blue-600 hover:underline"
                                            >
                                                Details
                                            </Link>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppShell>
    );
}

/* Minimal layout wrapper so the page renders without the full app shell. */
function AppShell({
    application,
    children,
}: {
    application: { id: number; application_reference: string; status: string };
    children: React.ReactNode;
}) {
    return (
        <div className="mx-auto max-w-3xl p-6">
            <Head title={`Documents — ${application.application_reference}`} />
            <div className="mb-4">
                <Link
                    href={`/applications/${application.id}/wizard`}
                    className="text-sm text-blue-600 hover:underline"
                >
                    ← Back to application
                </Link>
                <h1 className="text-xl font-semibold">Supporting Documents</h1>
                <p className="text-sm text-muted-foreground">
                    {application.application_reference} · {application.status}
                </p>
            </div>
            {children}
        </div>
    );
}
