import { Head, Link, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

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

interface Decision {
    value: string;
    label: string;
}

interface Props {
    application: {
        id: number;
        application_reference: string;
        institution: string | null;
        program: string | null;
    };
    document: {
        id: number;
        status: string;
        status_label: string;
        requirement_code: string | null;
        requirement_title: string | null;
        filename: string | null;
        mime_type: string | null;
        file_size_bytes: number;
        uploaded_by: string | null;
        uploaded_at: string | null;
        replacement_needed: boolean;
        versions: Version[];
        reviews: Review[];
    };
    decisions: Decision[];
    can_review: boolean;
}

export default function ReviewerDocumentShow({ application, document: doc, decisions, can_review }: Props) {
    const { data, setData, post, processing, errors } = useForm<{
        decision: string;
        comment: string;
    }>({
        decision: '',
        comment: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/reviewer/documents/${doc.id}/review`);
    }

    return (
        <div className="mx-auto max-w-3xl p-6">
            <Head title="Review document" />
            <div className="mb-4">
                <Link href={`/reviewer/applications/${application.id}/documents`} className="text-sm text-blue-600 hover:underline">
                    ← Back to documents
                </Link>
                <h1 className="text-xl font-semibold">
                    {doc.requirement_code ? `${doc.requirement_code} — ` : ''}
                    {doc.requirement_title ?? 'Application document'}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {application.application_reference} · {application.institution}
                    {application.program ? ` · ${application.program}` : ''} · Status: {doc.status_label}
                </p>
            </div>

            <Card className="mb-4">
                <CardHeader>
                    <CardTitle>Current file</CardTitle>
                </CardHeader>
                <CardContent className="space-y-3">
                    <p className="text-sm">
                        {doc.filename} · {doc.uploaded_by}
                        {doc.uploaded_at ? ` · ${doc.uploaded_at}` : ''}
                    </p>
                    <div className="flex gap-3">
                        <a href={`/reviewer/documents/${doc.id}/preview`} target="_blank" className="text-sm text-blue-600 hover:underline">
                            Preview
                        </a>
                        <a href={`/reviewer/documents/${doc.id}/download`} className="text-sm text-blue-600 hover:underline">
                            Download
                        </a>
                    </div>
                </CardContent>
            </Card>

            {can_review && (
                <Card className="mb-4">
                    <CardHeader>
                        <CardTitle>Review decision</CardTitle>
                        <CardDescription>
                            A comment is required when rejecting, requesting a replacement, or returning to review.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="decision">Decision</Label>
                                <select
                                    id="decision"
                                    value={data.decision}
                                    onChange={(e) => setData('decision', e.target.value)}
                                    className="mt-1 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                >
                                    <option value="">Select a decision…</option>
                                    {decisions.map((d) => (
                                        <option key={d.value} value={d.value}>
                                            {d.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.decision} />
                            </div>
                            <div>
                                <Label htmlFor="comment">Comment</Label>
                                <Textarea
                                    id="comment"
                                    value={data.comment}
                                    onChange={(e) => setData('comment', e.target.value)}
                                    placeholder="Required for reject / replacement request / return to review"
                                />
                                <InputError message={errors.comment} />
                            </div>
                            <Button type="submit" disabled={processing || !data.decision}>
                                Record decision
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}

            <Card className="mb-4">
                <CardHeader>
                    <CardTitle>Version history</CardTitle>
                </CardHeader>
                <CardContent>
                    <ul className="divide-y">
                        {doc.versions.map((v) => (
                            <li key={v.version_number} className="py-2 text-sm">
                                <span className="font-medium">v{v.version_number}</span> · {v.original_filename} ·{' '}
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
                        <p className="text-sm text-muted-foreground">No reviews yet.</p>
                    ) : (
                        <ul className="divide-y">
                            {doc.reviews.map((r, i) => (
                                <li key={i} className="py-2 text-sm">
                                    <span className="font-medium">{r.decision_label}</span>
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
