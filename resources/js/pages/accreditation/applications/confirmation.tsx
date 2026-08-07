import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';

interface ConfirmationApplication {
    id: number;
    reference: string;
    institution: string | null;
    program: string | null;
    application_type: string;
    status_label: string;
    submitted_at: string | null;
    submitted_by: string | null;
    checklist_version: string | null;
}

export default function SubmissionConfirmation({
    application,
}: {
    application: ConfirmationApplication;
}) {
    return (
        <>
            <Head title="Application Submitted" />
            <div className="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-4 p-4">
                <Card>
                    <CardHeader>
                        <CardTitle className="text-2xl">
                            Application submitted
                        </CardTitle>
                        <CardDescription>
                            Your accreditation application has been received.
                            Keep the reference number below for your records.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="flex flex-col gap-4">
                        <div className="rounded-lg border bg-muted/40 p-4">
                            <p className="text-xs tracking-wide text-muted-foreground uppercase">
                                Reference number
                            </p>
                            <p className="font-mono text-xl font-semibold">
                                {application.reference}
                            </p>
                        </div>

                        <dl className="grid gap-3 sm:grid-cols-2">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Institution
                                </dt>
                                <dd className="text-sm">
                                    {application.institution ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Program
                                </dt>
                                <dd className="text-sm">
                                    {application.program ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Application type
                                </dt>
                                <dd className="text-sm">
                                    {application.application_type}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Status
                                </dt>
                                <dd className="text-sm">
                                    {application.status_label}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Submitted
                                </dt>
                                <dd className="text-sm">
                                    {application.submitted_at ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Submitted by
                                </dt>
                                <dd className="text-sm">
                                    {application.submitted_by ?? '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Checklist version
                                </dt>
                                <dd className="text-sm">
                                    {application.checklist_version ?? '—'}
                                </dd>
                            </div>
                        </dl>

                        <p className="text-sm text-muted-foreground">
                            The application is now locked for editing. Review
                            scheduling and inspection are handled in a later
                            stage and are not yet available.
                        </p>

                        <div>
                            <Button asChild>
                                <Link href="/applications">
                                    Back to applications
                                </Link>
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
