import { Head, Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';

interface Doc {
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
}

interface Props {
    application: {
        id: number;
        application_reference: string;
        institution: string | null;
        program: string | null;
    };
    documents: Doc[];
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

export default function ReviewerDocumentIndex({
    application,
    documents,
}: Props) {
    return (
        <div className="mx-auto max-w-4xl p-6">
            <Head title={`Documents — ${application.application_reference}`} />
            <h1 className="text-xl font-semibold">Supporting documents</h1>
            <p className="text-sm text-muted-foreground">
                {application.application_reference} · {application.institution}
                {application.program ? ` · ${application.program}` : ''}
            </p>

            <Card className="mt-4">
                <CardHeader>
                    <CardTitle>Documents ({documents.length})</CardTitle>
                </CardHeader>
                <CardContent>
                    {documents.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            No documents uploaded.
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
                                            {doc.filename} · {doc.uploaded_by}
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
                                            href={`/reviewer/documents/${doc.id}`}
                                            className="text-sm text-blue-600 hover:underline"
                                        >
                                            Review
                                        </Link>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardContent>
            </Card>
        </div>
    );
}
