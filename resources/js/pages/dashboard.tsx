import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { dashboard } from '@/routes';

interface ProgramSummary {
    id: number;
    name: string;
    type: string;
    status: string;
    accredited_until: string | null;
    has_active_accreditation: boolean;
}

interface DashboardSummary {
    scope: 'system' | 'institution';
    roles: { name: string; label: string }[];
    institution: {
        id: number;
        name: string;
        code: string;
        status: string;
    } | null;
    programs: ProgramSummary[];
    application_count: number;
    open_application_count: number;
    active_accreditation: {
        is_active: boolean;
        accredited_program_count: number;
        next_expiry: string | null;
    };
    missing_foundation_data: string[];
}

export default function Dashboard({ summary }: { summary: DashboardSummary }) {
    const {
        institution,
        programs,
        active_accreditation: accreditation,
    } = summary;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader>
                            <CardDescription>Institution</CardDescription>
                            <CardTitle className="text-xl">
                                {institution
                                    ? institution.name
                                    : summary.scope === 'system'
                                      ? 'All institutions'
                                      : 'Not assigned'}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            {institution
                                ? `${institution.code} · ${institution.status}`
                                : 'No institution membership on this account.'}
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Accreditation applications
                            </CardDescription>
                            <CardTitle className="text-3xl tabular-nums">
                                {summary.application_count}
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            {summary.open_application_count} open in the
                            workflow
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardDescription>
                                Active accreditation
                            </CardDescription>
                            <CardTitle className="text-xl">
                                <Badge
                                    variant={
                                        accreditation.is_active
                                            ? 'default'
                                            : 'secondary'
                                    }
                                >
                                    {accreditation.is_active
                                        ? 'Active'
                                        : 'None recorded'}
                                </Badge>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="text-sm text-muted-foreground">
                            {accreditation.is_active
                                ? `${accreditation.accredited_program_count} accredited program(s)${
                                      accreditation.next_expiry
                                          ? ` · next expiry ${accreditation.next_expiry}`
                                          : ''
                                  }`
                                : 'No accreditation decision is currently in force.'}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Programs</CardTitle>
                        <CardDescription>
                            Training programs visible to your role.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {programs.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No programs registered.
                            </p>
                        ) : (
                            <ul className="divide-y divide-border">
                                {programs.map((program) => (
                                    <li
                                        key={program.id}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div>
                                            <p className="font-medium">
                                                {program.name}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {program.type}
                                            </p>
                                        </div>
                                        <div className="text-right text-sm">
                                            <Badge
                                                variant={
                                                    program.has_active_accreditation
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                            >
                                                {program.status}
                                            </Badge>
                                            {program.accredited_until && (
                                                <p className="mt-1 text-muted-foreground">
                                                    Until{' '}
                                                    {program.accredited_until}
                                                </p>
                                            )}
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Missing foundation data</CardTitle>
                        <CardDescription>
                            Items still required before the accreditation
                            workflow can run.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        {summary.missing_foundation_data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Foundation data is complete.
                            </p>
                        ) : (
                            <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                {summary.missing_foundation_data.map((item) => (
                                    <li key={item}>{item}</li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
