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
import { Label } from '@/components/ui/label';

interface ProgramOption {
    id: number;
    name: string;
    type: string;
    institution: string | null;
    status: string;
}

interface TypeOption {
    value: string;
    label: string;
}

export default function StartApplication({
    programs,
    application_types: applicationTypes,
}: {
    programs: ProgramOption[];
    application_types: TypeOption[];
}) {
    const { data, setData, post, processing, errors } = useForm({
        program_id: '',
        application_type: applicationTypes[0]?.value ?? 'new',
    });

    return (
        <>
            <Head title="Start Application" />
            <div className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Start an accreditation application
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Choose the training program and application type. A
                        draft is saved immediately.
                    </p>
                </div>

                {programs.length === 0 ? (
                    <Card>
                        <CardHeader>
                            <CardTitle>No eligible programs</CardTitle>
                            <CardDescription>
                                Your institution has no active training programs
                                available for a new application.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <Button asChild variant="outline">
                                <Link href="/applications">
                                    Back to applications
                                </Link>
                            </Button>
                        </CardContent>
                    </Card>
                ) : (
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            post('/applications');
                        }}
                        className="flex flex-col gap-6"
                        noValidate
                    >
                        <Card>
                            <CardHeader>
                                <CardTitle>
                                    Step 1 — Program selection
                                </CardTitle>
                                <CardDescription>
                                    Only programs belonging to your institution
                                    are listed.
                                </CardDescription>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                <fieldset className="flex flex-col gap-2">
                                    <legend className="sr-only">
                                        Select a training program
                                    </legend>
                                    {programs.map((program) => (
                                        <label
                                            key={program.id}
                                            className="flex cursor-pointer items-start gap-3 rounded-lg border p-3 hover:bg-accent has-[:checked]:border-primary has-[:checked]:bg-accent"
                                        >
                                            <input
                                                type="radio"
                                                name="program_id"
                                                value={program.id}
                                                checked={
                                                    data.program_id ===
                                                    String(program.id)
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        'program_id',
                                                        e.target.value,
                                                    )
                                                }
                                                className="mt-1"
                                                aria-describedby={`program-${program.id}-meta`}
                                            />
                                            <span>
                                                <span className="block font-medium">
                                                    {program.name}
                                                </span>
                                                <span
                                                    id={`program-${program.id}-meta`}
                                                    className="block text-sm text-muted-foreground"
                                                >
                                                    {program.type} ·{' '}
                                                    {program.institution} ·{' '}
                                                    {program.status}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </fieldset>
                                <InputError message={errors.program_id} />
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle>Application type</CardTitle>
                            </CardHeader>
                            <CardContent className="flex flex-col gap-3">
                                <Label htmlFor="application_type">Type</Label>
                                <select
                                    id="application_type"
                                    name="application_type"
                                    value={data.application_type}
                                    onChange={(e) =>
                                        setData(
                                            'application_type',
                                            e.target.value,
                                        )
                                    }
                                    className="w-full rounded-md border border-input bg-background p-2 text-sm"
                                >
                                    {applicationTypes.map((type) => (
                                        <option
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.application_type} />
                            </CardContent>
                        </Card>

                        <div className="flex gap-3">
                            <Button type="submit" disabled={processing}>
                                Create draft
                            </Button>
                            <Button asChild type="button" variant="outline">
                                <Link href="/applications">Cancel</Link>
                            </Button>
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}
