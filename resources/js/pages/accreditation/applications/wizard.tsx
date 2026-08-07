import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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

/* ------------------------------------------------------------------ types */

interface StepInfo {
    key: string;
    label: string;
    position: number;
    is_complete: boolean;
    is_current: boolean;
    missing_fields: string[];
}

interface Progress {
    steps: StepInfo[];
    completion_percentage: number;
    required_fields_remaining: string[];
    current_step: string;
    status: string;
    status_label: string;
}

interface ChecklistItem {
    id: number;
    code: string;
    requirement: string;
    guidance: string | null;
    severity: string;
    severity_label: string;
    is_mandatory: boolean;
    applicant_status: string;
    applicant_response: string | null;
    applicant_explanation: string | null;
    responded_at: string | null;
}

interface ChecklistGroup {
    section: string;
    items: ChecklistItem[];
}

interface ResponseStatus {
    value: string;
    label: string;
    requires_explanation: boolean;
}

interface WizardApplication {
    id: number;
    reference: string;
    institution: string | null;
    program: string | null;
    program_type: string | null;
    application_type: string;
    application_type_label: string;
    checklist_version: string | null;
    status: string;
    status_label: string;
    current_step: string;
    completion_percentage: number;
    declaration_accepted_at: string | null;
    sections: Record<string, Record<string, string | number | null>>;
}

interface Props {
    application: WizardApplication;
    progress: Progress;
    checklist: ChecklistGroup[];
    response_statuses: ResponseStatus[];
    can_edit: boolean;
    can_submit: boolean;
}

/* ---------------------------------------------------------- field configs */

type FieldDef = {
    name: string;
    label: string;
    type: 'text' | 'number' | 'textarea';
    required: boolean;
};

const STEP_FIELDS: Record<string, { section: string; fields: FieldDef[] }> = {
    application_details: {
        section: 'program_details',
        fields: [
            {
                name: 'program_head',
                label: 'Program head',
                type: 'text',
                required: true,
            },
            {
                name: 'training_duration_years',
                label: 'Training duration (years)',
                type: 'number',
                required: true,
            },
            {
                name: 'approved_resident_slots',
                label: 'Approved resident slots',
                type: 'number',
                required: true,
            },
            {
                name: 'program_description',
                label: 'Program description',
                type: 'textarea',
                required: false,
            },
        ],
    },
    institution_information: {
        section: 'institution_information',
        fields: [
            {
                name: 'bed_capacity',
                label: 'Bed capacity',
                type: 'number',
                required: true,
            },
            {
                name: 'ownership_type',
                label: 'Ownership type',
                type: 'text',
                required: true,
            },
            {
                name: 'accrediting_bodies',
                label: 'Other accrediting bodies',
                type: 'textarea',
                required: false,
            },
            {
                name: 'institution_notes',
                label: 'Additional notes',
                type: 'textarea',
                required: false,
            },
        ],
    },
    leadership_staffing: {
        section: 'leadership_staffing',
        fields: [
            {
                name: 'program_director',
                label: 'Program director',
                type: 'text',
                required: true,
            },
            {
                name: 'consultant_count',
                label: 'Number of consultants',
                type: 'number',
                required: true,
            },
            {
                name: 'support_staff_count',
                label: 'Support staff count',
                type: 'number',
                required: false,
            },
            {
                name: 'staffing_notes',
                label: 'Staffing notes',
                type: 'textarea',
                required: false,
            },
        ],
    },
    facilities_services: {
        section: 'facilities_services',
        fields: [
            {
                name: 'laboratory_sections',
                label: 'Laboratory sections',
                type: 'textarea',
                required: true,
            },
            {
                name: 'annual_specimen_volume',
                label: 'Annual specimen volume',
                type: 'number',
                required: true,
            },
            {
                name: 'equipment_summary',
                label: 'Equipment summary',
                type: 'textarea',
                required: false,
            },
        ],
    },
    curriculum_training: {
        section: 'curriculum_training',
        fields: [
            {
                name: 'curriculum_summary',
                label: 'Curriculum summary',
                type: 'textarea',
                required: true,
            },
            {
                name: 'rotation_schedule',
                label: 'Rotation schedule',
                type: 'textarea',
                required: true,
            },
            {
                name: 'evaluation_methods',
                label: 'Evaluation methods',
                type: 'textarea',
                required: false,
            },
        ],
    },
};

function severityVariant(
    severity: string,
): 'default' | 'secondary' | 'outline' {
    if (severity === 'major') {
        return 'default';
    }

    if (severity === 'minor') {
        return 'secondary';
    }

    return 'outline';
}

/* ------------------------------------------------------------- sub-views */

function StepForm({
    application,
    stepKey,
    canEdit,
}: {
    application: WizardApplication;
    stepKey: string;
    canEdit: boolean;
}) {
    const config = STEP_FIELDS[stepKey];
    const stored = application.sections[config.section] ?? {};

    const initial: Record<string, string> = {};
    config.fields.forEach((f) => {
        const v = stored[f.name];
        initial[f.name] = v === null || v === undefined ? '' : String(v);
    });

    // Only the field values live in form state. The step is taken from the
    // prop at submit time so it can never drift from the rendered step.
    const { data, setData, post, processing, errors, transform } = useForm<{
        payload: Record<string, string>;
    }>({
        payload: initial,
    });

    const payload = data.payload;

    const save = (advance: boolean) => {
        transform((current) => ({
            ...current,
            step: stepKey,
            advance,
        }));

        post(`/applications/${application.id}/wizard/step`, {
            preserveScroll: true,
        });
    };

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                save(true);
            }}
            className="flex flex-col gap-4"
            noValidate
        >
            {config.fields.map((field) => {
                const errorKey = `payload.${field.name}`;
                const errorMsg = (errors as Record<string, string>)[errorKey];
                const fieldId = `field-${field.name}`;

                return (
                    <div key={field.name} className="flex flex-col gap-1.5">
                        <Label htmlFor={fieldId}>
                            {field.label}
                            {field.required && (
                                <span
                                    className="ml-1 text-destructive"
                                    aria-hidden="true"
                                >
                                    *
                                </span>
                            )}
                        </Label>
                        {field.type === 'textarea' ? (
                            <textarea
                                id={fieldId}
                                name={field.name}
                                rows={4}
                                disabled={!canEdit}
                                required={field.required}
                                aria-required={field.required}
                                aria-invalid={errorMsg ? true : undefined}
                                aria-describedby={
                                    errorMsg ? `${fieldId}-error` : undefined
                                }
                                value={payload[field.name] ?? ''}
                                onChange={(e) =>
                                    setData('payload', {
                                        ...payload,
                                        [field.name]: e.target.value,
                                    })
                                }
                                className="w-full rounded-md border border-input bg-background p-2 text-sm disabled:opacity-60"
                            />
                        ) : (
                            <Input
                                id={fieldId}
                                name={field.name}
                                type={field.type}
                                disabled={!canEdit}
                                required={field.required}
                                aria-required={field.required}
                                aria-invalid={errorMsg ? true : undefined}
                                aria-describedby={
                                    errorMsg ? `${fieldId}-error` : undefined
                                }
                                value={payload[field.name] ?? ''}
                                onChange={(e) =>
                                    setData('payload', {
                                        ...payload,
                                        [field.name]: e.target.value,
                                    })
                                }
                            />
                        )}
                        <div id={`${fieldId}-error`}>
                            <InputError message={errorMsg} />
                        </div>
                    </div>
                );
            })}

            {canEdit && (
                <div className="flex flex-wrap gap-3">
                    <Button
                        type="button"
                        variant="outline"
                        disabled={processing}
                        onClick={() => save(false)}
                    >
                        Save draft
                    </Button>
                    <Button type="submit" disabled={processing}>
                        Save and continue
                    </Button>
                </div>
            )}
        </form>
    );
}

function ChecklistStep({
    application,
    checklist,
    statuses,
    canEdit,
}: {
    application: WizardApplication;
    checklist: ChecklistGroup[];
    statuses: ResponseStatus[];
    canEdit: boolean;
}) {
    const [errors, setErrors] = useState<Record<number, string>>({});
    const [drafts, setDrafts] = useState<
        Record<
            number,
            { status: string; response: string; explanation: string }
        >
    >(() => {
        const seed: Record<
            number,
            { status: string; response: string; explanation: string }
        > = {};
        checklist.forEach((group) =>
            group.items.forEach((item) => {
                seed[item.id] = {
                    status: item.applicant_status,
                    response: item.applicant_response ?? '',
                    explanation: item.applicant_explanation ?? '',
                };
            }),
        );

        return seed;
    });

    const update = (
        id: number,
        patch: Partial<{
            status: string;
            response: string;
            explanation: string;
        }>,
    ) => setDrafts((prev) => ({ ...prev, [id]: { ...prev[id], ...patch } }));

    const save = (item: ChecklistItem) => {
        const draft = drafts[item.id];

        // Client-side mirror of the server rule.
        if (
            draft.status === 'not_applicable' &&
            draft.explanation.trim() === ''
        ) {
            setErrors((prev) => ({
                ...prev,
                [item.id]:
                    'An explanation is required when a requirement is marked not applicable.',
            }));

            return;
        }

        setErrors((prev) => {
            const next = { ...prev };
            delete next[item.id];

            return next;
        });

        router.post(
            `/applications/${application.id}/wizard/checklist`,
            {
                requirement_id: item.id,
                applicant_status: draft.status,
                applicant_response: draft.response,
                applicant_explanation: draft.explanation,
            },
            { preserveScroll: true, preserveState: true },
        );
    };

    if (checklist.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                No checklist requirements are configured for this program type.
            </p>
        );
    }

    return (
        <div className="flex flex-col gap-6">
            <p className="text-sm text-muted-foreground">
                Checklist version{' '}
                <span className="font-mono">
                    {application.checklist_version}
                </span>
                . Requirements are configured by PSP and cannot be edited here.
            </p>

            {checklist.map((group) => (
                <section key={group.section} className="flex flex-col gap-3">
                    <h3 className="text-lg font-medium">{group.section}</h3>

                    {group.items.map((item) => {
                        const draft = drafts[item.id];
                        const needsExplanation =
                            draft.status === 'not_applicable';

                        return (
                            <Card key={item.id}>
                                <CardHeader>
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <CardTitle className="text-base font-medium">
                                            <span className="mr-2 font-mono text-xs text-muted-foreground">
                                                {item.code}
                                            </span>
                                            {item.requirement}
                                        </CardTitle>
                                        <div className="flex gap-2">
                                            <Badge
                                                variant={severityVariant(
                                                    item.severity,
                                                )}
                                            >
                                                {item.severity_label}
                                            </Badge>
                                            {item.is_mandatory && (
                                                <Badge variant="outline">
                                                    Mandatory
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                    {item.guidance && (
                                        <CardDescription>
                                            {item.guidance}
                                        </CardDescription>
                                    )}
                                </CardHeader>
                                <CardContent className="flex flex-col gap-3">
                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor={`status-${item.id}`}>
                                            Response status
                                        </Label>
                                        <select
                                            id={`status-${item.id}`}
                                            disabled={!canEdit}
                                            value={draft.status}
                                            onChange={(e) =>
                                                update(item.id, {
                                                    status: e.target.value,
                                                })
                                            }
                                            className="w-full max-w-xs rounded-md border border-input bg-background p-2 text-sm disabled:opacity-60"
                                        >
                                            <option value="not_started">
                                                Not started
                                            </option>
                                            {statuses.map((s) => (
                                                <option
                                                    key={s.value}
                                                    value={s.value}
                                                >
                                                    {s.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>

                                    <div className="flex flex-col gap-1.5">
                                        <Label htmlFor={`response-${item.id}`}>
                                            Applicant response
                                        </Label>
                                        <textarea
                                            id={`response-${item.id}`}
                                            rows={3}
                                            disabled={!canEdit}
                                            value={draft.response}
                                            onChange={(e) =>
                                                update(item.id, {
                                                    response: e.target.value,
                                                })
                                            }
                                            className="w-full rounded-md border border-input bg-background p-2 text-sm disabled:opacity-60"
                                        />
                                    </div>

                                    {needsExplanation && (
                                        <div className="flex flex-col gap-1.5">
                                            <Label
                                                htmlFor={`explanation-${item.id}`}
                                            >
                                                Explanation
                                                <span
                                                    className="ml-1 text-destructive"
                                                    aria-hidden="true"
                                                >
                                                    *
                                                </span>
                                            </Label>
                                            <textarea
                                                id={`explanation-${item.id}`}
                                                rows={2}
                                                disabled={!canEdit}
                                                required
                                                aria-required="true"
                                                aria-invalid={
                                                    errors[item.id]
                                                        ? true
                                                        : undefined
                                                }
                                                value={draft.explanation}
                                                onChange={(e) =>
                                                    update(item.id, {
                                                        explanation:
                                                            e.target.value,
                                                    })
                                                }
                                                className="w-full rounded-md border border-input bg-background p-2 text-sm disabled:opacity-60"
                                            />
                                            <InputError
                                                message={errors[item.id]}
                                            />
                                        </div>
                                    )}

                                    {canEdit && (
                                        <div>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                onClick={() => save(item)}
                                            >
                                                Save response
                                            </Button>
                                            {item.responded_at && (
                                                <span className="ml-3 text-xs text-muted-foreground">
                                                    Last saved{' '}
                                                    {item.responded_at}
                                                </span>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        );
                    })}
                </section>
            ))}
        </div>
    );
}

function ReviewStep({
    application,
    progress,
    canEdit,
    canSubmit,
}: {
    application: WizardApplication;
    progress: Progress;
    canEdit: boolean;
    canSubmit: boolean;
}) {
    const { data, setData, post, processing, errors } = useForm({
        declaration_accepted: false,
    });
    const accepted = application.declaration_accepted_at !== null;
    const outstanding = progress.required_fields_remaining;

    return (
        <div className="flex flex-col gap-6">
            <Card>
                <CardHeader>
                    <CardTitle>Review</CardTitle>
                    <CardDescription>
                        Confirm the information below before submitting.
                    </CardDescription>
                </CardHeader>
                <CardContent className="flex flex-col gap-4">
                    {Object.entries(application.sections).map(
                        ([section, values]) => (
                            <div key={section}>
                                <h4 className="mb-1 text-sm font-medium capitalize">
                                    {section.replace(/_/g, ' ')}
                                </h4>
                                {Object.keys(values).length === 0 ? (
                                    <p className="text-sm text-muted-foreground">
                                        Not yet provided.
                                    </p>
                                ) : (
                                    <dl className="grid gap-2 sm:grid-cols-2">
                                        {Object.entries(values).map(
                                            ([key, value]) => (
                                                <div key={key}>
                                                    <dt className="text-xs text-muted-foreground capitalize">
                                                        {key.replace(/_/g, ' ')}
                                                    </dt>
                                                    <dd className="text-sm">
                                                        {value === null ||
                                                        value === ''
                                                            ? '—'
                                                            : String(value)}
                                                    </dd>
                                                </div>
                                            ),
                                        )}
                                    </dl>
                                )}
                            </div>
                        ),
                    )}
                </CardContent>
            </Card>

            {outstanding.length > 0 && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Outstanding items
                        </CardTitle>
                        <CardDescription>
                            These must be resolved before the application can be
                            submitted.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                            {outstanding.map((item) => (
                                <li key={item}>{item}</li>
                            ))}
                        </ul>
                    </CardContent>
                </Card>
            )}

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">Declaration</CardTitle>
                </CardHeader>
                <CardContent className="flex flex-col gap-3">
                    {accepted ? (
                        <p className="text-sm text-muted-foreground">
                            Declaration accepted on{' '}
                            {application.declaration_accepted_at}.
                        </p>
                    ) : (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                post(
                                    `/applications/${application.id}/wizard/declaration`,
                                    { preserveScroll: true },
                                );
                            }}
                            className="flex flex-col gap-3"
                            noValidate
                        >
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    className="mt-1"
                                    checked={data.declaration_accepted}
                                    disabled={!canEdit}
                                    onChange={(e) =>
                                        setData(
                                            'declaration_accepted',
                                            e.target.checked,
                                        )
                                    }
                                    aria-describedby="declaration-error"
                                />
                                <span>
                                    I declare that the information provided in
                                    this application is true, complete and
                                    accurate to the best of my knowledge.
                                </span>
                            </label>
                            <div id="declaration-error">
                                <InputError
                                    message={errors.declaration_accepted}
                                />
                            </div>
                            {canEdit && (
                                <div>
                                    <Button type="submit" disabled={processing}>
                                        Accept declaration
                                    </Button>
                                </div>
                            )}
                        </form>
                    )}
                </CardContent>
            </Card>

            {canSubmit && (
                <Card>
                    <CardHeader>
                        <CardTitle className="text-base">
                            Submit application
                        </CardTitle>
                        <CardDescription>
                            Once submitted the application is locked and can no
                            longer be edited.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button
                            type="button"
                            disabled={!accepted}
                            onClick={() =>
                                router.post(
                                    `/applications/${application.id}/submit`,
                                )
                            }
                        >
                            Submit application
                        </Button>
                    </CardContent>
                </Card>
            )}
        </div>
    );
}

/* ------------------------------------------------------------------ page */

export default function ApplicationWizard({
    application,
    progress,
    checklist,
    response_statuses: statuses,
    can_edit: canEdit,
    can_submit: canSubmit,
}: Props) {
    const current = progress.current_step;

    const goToStep = (stepKey: string) =>
        router.post(
            `/applications/${application.id}/wizard/navigate`,
            { step: stepKey },
            { preserveScroll: true },
        );

    const currentIndex = progress.steps.findIndex((s) => s.key === current);
    const previousStep =
        currentIndex > 0 ? progress.steps[currentIndex - 1] : null;
    const nextStep =
        currentIndex >= 0 && currentIndex < progress.steps.length - 1
            ? progress.steps[currentIndex + 1]
            : null;

    return (
        <>
            <Head title={`Application ${application.reference}`} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-semibold">
                            {application.program ?? 'Application'}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <span className="font-mono">
                                {application.reference}
                            </span>{' '}
                            · {application.institution} ·{' '}
                            {application.application_type_label}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Badge
                            variant={
                                application.status === 'draft'
                                    ? 'secondary'
                                    : 'default'
                            }
                        >
                            {application.status_label}
                        </Badge>
                        <Button asChild variant="outline" size="sm">
                            <Link href="/applications">Back to list</Link>
                        </Button>
                    </div>
                </div>

                {!canEdit && (
                    <div
                        role="status"
                        className="rounded-md border border-amber-500/40 bg-amber-500/10 p-3 text-sm"
                    >
                        This application is read-only. Submitted applications
                        cannot be edited by applicants.
                    </div>
                )}

                <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
                    {/* Progress sidebar */}
                    <Card className="h-fit">
                        <CardHeader>
                            <CardTitle className="text-base">
                                Progress
                            </CardTitle>
                            <CardDescription>
                                {progress.completion_percentage}% complete
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="flex flex-col gap-3">
                            <div
                                className="h-2 w-full overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuenow={progress.completion_percentage}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label="Overall application completion"
                            >
                                <div
                                    className="h-full bg-primary"
                                    style={{
                                        width: `${progress.completion_percentage}%`,
                                    }}
                                />
                            </div>

                            <nav aria-label="Wizard steps">
                                <ol className="flex flex-col gap-1">
                                    {progress.steps.map((step) => (
                                        <li key={step.key}>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    goToStep(step.key)
                                                }
                                                aria-current={
                                                    step.key === current
                                                        ? 'step'
                                                        : undefined
                                                }
                                                className={`flex w-full items-center gap-2 rounded-md p-2 text-left text-sm transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none ${
                                                    step.key === current
                                                        ? 'bg-accent font-medium'
                                                        : ''
                                                }`}
                                            >
                                                <span
                                                    aria-hidden="true"
                                                    className={`flex size-5 shrink-0 items-center justify-center rounded-full border text-[10px] ${
                                                        step.is_complete
                                                            ? 'border-primary bg-primary text-primary-foreground'
                                                            : 'border-muted-foreground/40'
                                                    }`}
                                                >
                                                    {step.is_complete
                                                        ? '✓'
                                                        : step.position}
                                                </span>
                                                <span className="flex-1">
                                                    {step.label}
                                                </span>
                                                <span className="sr-only">
                                                    {step.is_complete
                                                        ? '(complete)'
                                                        : '(incomplete)'}
                                                </span>
                                            </button>
                                        </li>
                                    ))}
                                </ol>
                            </nav>
                        </CardContent>
                    </Card>

                    {/* Step body */}
                    <div className="flex flex-col gap-4">
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    {
                                        progress.steps.find(
                                            (s) => s.key === current,
                                        )?.label
                                    }
                                </CardTitle>
                                <CardDescription>
                                    Step{' '}
                                    {
                                        progress.steps.find(
                                            (s) => s.key === current,
                                        )?.position
                                    }{' '}
                                    of {progress.steps.length}
                                </CardDescription>
                            </CardHeader>
                            <CardContent>
                                {current === 'program_selection' && (
                                    <dl className="grid gap-3 sm:grid-cols-2">
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
                                                Program type
                                            </dt>
                                            <dd className="text-sm">
                                                {application.program_type ??
                                                    '—'}
                                            </dd>
                                        </div>
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
                                                Application type
                                            </dt>
                                            <dd className="text-sm">
                                                {
                                                    application.application_type_label
                                                }
                                            </dd>
                                        </div>
                                    </dl>
                                )}

                                {STEP_FIELDS[current] && (
                                    <StepForm
                                        // Remount on step change so the form
                                        // re-seeds from the new step's stored
                                        // values instead of reusing the last
                                        // step's state.
                                        key={current}
                                        application={application}
                                        stepKey={current}
                                        canEdit={canEdit}
                                    />
                                )}

                                {current === 'checklist_responses' && (
                                    <ChecklistStep
                                        key={current}
                                        application={application}
                                        checklist={checklist}
                                        statuses={statuses}
                                        canEdit={canEdit}
                                    />
                                )}

                                {(current === 'review_declaration' ||
                                    current === 'submit_application') && (
                                    <ReviewStep
                                        application={application}
                                        progress={progress}
                                        canEdit={canEdit}
                                        canSubmit={canSubmit}
                                    />
                                )}
                            </CardContent>
                        </Card>

                        <div className="flex justify-between gap-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!previousStep}
                                onClick={() =>
                                    previousStep && goToStep(previousStep.key)
                                }
                            >
                                Back
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={!nextStep}
                                onClick={() =>
                                    nextStep && goToStep(nextStep.key)
                                }
                            >
                                Next
                            </Button>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}
