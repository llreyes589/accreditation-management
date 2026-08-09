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

interface Version {
    version_number: number;
    original_filename: string;
    mime_type: string;
    file_size_bytes: number;
    uploaded_at: string | null;
    uploaded_by: string | null;
    is_current: boolean;
    is_superseded: boolean;
}

interface Review {
    decision: string;
    decision_label: string;
    comment: string | null;
    reviewer: string | null;
    reviewed_at: string | null;
}

interface Props {
    application: { id: number; application_reference: string };
    document: {
        id: number;
        status: string;
        status_label: string;
        requirement_code: string | null;
        requirement_title: string | null;
        is_mandatory: boolean;
        filename: string | null;
        uploaded_at: string | null;
        replacement_needed: boolean;
        versions: Version[];
        reviews: Review[];
    };
    can_replace: boolean;
    can_withdraw: boolean;
}

export default function DocumentShow({
    application,
    document: doc,
    can_replace,
    can_withdraw,
}: Props) {
    const { data, setData, post, processing, errors, reset } = useForm<{
        document: File | null;
    }>({
        document: null,
    });

    function replace(e: React.FormEvent) {
        e.preventDefault();
        post(`/applications/${application.id}/documents/${doc.id}/replace`, {
            forceFormData: true,
            onSuccess: () => reset('document'),
        });
    }

    function withdraw() {
        if (
            !confirm(
                'Withdraw this document? This is a logical removal and can be audited.',
            )
        ) {
            return;
        }

        post(`/applications/${application.id}/documents/${doc.id}/withdraw`);
    }

    return (
        <div className="mx-auto max-w-3xl p-6">
            <Head title="Document details" />
            <div className="mb-4">
                <Link
                    href={`/applications/${application.id}/documents`}
                    className="text-sm text-blue-600 hover:underline"
                >
                    ← Back to documents
                </Link>
                <h1 className="text-xl font-semibold">
                    {doc.requirement_code ? `${doc.requirement_code} — ` : ''}
                    {doc.requirement_title ?? 'Application document'}
                </h1>
                <span className="text-sm text-muted-foreground">
                    Status: {doc.status_label}
                    {doc.replacement_needed ? ' · replacement needed' : ''}
                </span>
            </div>

            <Card className="mb-4">
                <CardHeader>
                    <CardTitle>Current file</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <p className="text-sm">{doc.filename}</p>
                    <div className="flex gap-3">
                        <a
                            href={`/applications/${application.id}/documents/${doc.id}/preview`}
                            target="_blank"
                            className="text-sm text-blue-600 hover:underline"
                        >
                            Preview
                        </a>
                        <a
                            href={`/applications/${application.id}/documents/${doc.id}/download`}
                            className="text-sm text-blue-600 hover:underline"
                        >
                            Download
                        </a>
                    </div>
                    {can_replace && (
                        <form
                            onSubmit={replace}
                            className="space-y-2 border-t pt-3"
                        >
                            <Label htmlFor="document">Upload replacement</Label>
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
                            <Button
                                type="submit"
                                disabled={processing || !data.document}
                            >
                                Upload replacement
                            </Button>
                        </form>
                    )}
                    {can_withdraw && (
                        <Button
                            variant="outline"
                            onClick={withdraw}
                            className="mt-2"
                        >
                            Withdraw document
                        </Button>
                    )}
                </CardContent>
            </Card>

            <Card className="mb-4">
                <CardHeader>
                    <CardTitle>Version history</CardTitle>
                    <CardDescription>
                        Previous versions are preserved for audit.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <ul className="divide-y">
                        {doc.versions.map((v) => (
                            <li key={v.version_number} className="py-2 text-sm">
                                <span className="font-medium">
                                    v{v.version_number}
                                </span>{' '}
                                · {v.original_filename} ·{' '}
                                {(v.file_size_bytes / 1024).toFixed(1)} KB
                                {v.uploaded_at ? ` · ${v.uploaded_at}` : ''}
                                {v.is_current ? ' · current' : ''}
                                {v.is_superseded ? ' · superseded' : ''}
                            </li>
                        ))}
                    </ul>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle>Review history</CardTitle>
                </CardHeader>
                <CardContent>
                    {doc.reviews.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No reviews yet.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {doc.reviews.map((r, i) => (
                                <li key={i} className="py-2 text-sm">
                                    <span className="font-medium">
                                        {r.decision_label}
                                    </span>
                                    {r.reviewer ? ` by ${r.reviewer}` : ''}
                                    {r.reviewed_at ? ` · ${r.reviewed_at}` : ''}
                                    {r.comment ? ` — ${r.comment}` : ''}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
