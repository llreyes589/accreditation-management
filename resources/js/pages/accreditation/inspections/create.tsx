import { Head, useForm } from '@inertiajs/react';
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

interface CreateInspectionProps {
    application: {
        id: number;
        application_reference: string;
        institution: string | null;
        program: string | null;
        program_type: string | null;
        application_type: string | null;
        completed_for_inspection_at: string | null;
        checklist_version: string | null;
    };
    inspectors: { id: number; name: string; email: string }[];
}

export default function InspectionCreate({
    application,
    inspectors,
}: CreateInspectionProps) {
    const form = useForm({
        application_id: application.id,
        inspection_type: 'onsite',
        scheduled_start_at: '',
        scheduled_end_at: '',
        timezone: 'Asia/Manila',
        location: '',
        meeting_url: '',
        instructions: '',
        lead_inspector_id: '',
        inspector_ids: [] as number[],
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(`/inspections/applications/${application.id}`);
    }

    function toggleInspector(id: number) {
        const set = new Set(form.data.inspector_ids);

        if (set.has(id)) {
            set.delete(id);
        } else {
            set.add(id);
        }

        form.setData('inspector_ids', [...set]);
    }

    return (
        <>
            <Head title="Schedule Inspection" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Schedule Inspection
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Application {application.application_reference} —{' '}
                        {application.institution} / {application.program}. An
                        on-site or virtual inspection may be scheduled for this
                        application that is complete for inspection.
                    </p>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Inspection Details</CardTitle>
                        <CardDescription>
                            Checklist version {application.checklist_version}{' '}
                            will be snapshotted at creation and preserved
                            historically.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                                <div className="space-y-1">
                                    <Label htmlFor="inspection_type">
                                        Inspection Type
                                    </Label>
                                    <select
                                        id="inspection_type"
                                        className="w-full rounded border px-2 py-1.5 text-sm"
                                        value={form.data.inspection_type}
                                        onChange={(e) =>
                                            form.setData(
                                                'inspection_type',
                                                e.target.value,
                                            )
                                        }
                                    >
                                        <option value="onsite">On-site</option>
                                        <option value="virtual">Virtual</option>
                                    </select>
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="timezone">Timezone</Label>
                                    <Input
                                        id="timezone"
                                        value={form.data.timezone}
                                        onChange={(e) =>
                                            form.setData(
                                                'timezone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="scheduled_start_at">
                                        Start (UTC)
                                    </Label>
                                    <Input
                                        id="scheduled_start_at"
                                        type="datetime-local"
                                        value={form.data.scheduled_start_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'scheduled_start_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-1">
                                    <Label htmlFor="scheduled_end_at">
                                        End (UTC)
                                    </Label>
                                    <Input
                                        id="scheduled_end_at"
                                        type="datetime-local"
                                        value={form.data.scheduled_end_at}
                                        onChange={(e) =>
                                            form.setData(
                                                'scheduled_end_at',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-1 md:col-span-2">
                                    <Label htmlFor="location">Location</Label>
                                    <Input
                                        id="location"
                                        value={form.data.location}
                                        onChange={(e) =>
                                            form.setData(
                                                'location',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-1 md:col-span-2">
                                    <Label htmlFor="meeting_url">
                                        Meeting URL (virtual)
                                    </Label>
                                    <Input
                                        id="meeting_url"
                                        value={form.data.meeting_url}
                                        onChange={(e) =>
                                            form.setData(
                                                'meeting_url',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="space-y-1 md:col-span-2">
                                    <Label htmlFor="instructions">
                                        Instructions
                                    </Label>
                                    <Textarea
                                        id="instructions"
                                        value={form.data.instructions}
                                        onChange={(e) =>
                                            form.setData(
                                                'instructions',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="lead_inspector_id">
                                    Lead Inspector
                                </Label>
                                <select
                                    id="lead_inspector_id"
                                    className="w-full rounded border px-2 py-1.5 text-sm"
                                    value={form.data.lead_inspector_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'lead_inspector_id',
                                            e.target.value,
                                        )
                                    }
                                >
                                    <option value="">
                                        Select lead inspector
                                    </option>
                                    {inspectors.map((i) => (
                                        <option key={i.id} value={i.id}>
                                            {i.name} ({i.email})
                                        </option>
                                    ))}
                                </select>
                                {form.errors.lead_inspector_id && (
                                    <p className="text-xs text-destructive">
                                        {form.errors.lead_inspector_id}
                                    </p>
                                )}
                            </div>

                            <div className="space-y-2">
                                <Label>Additional Inspectors</Label>
                                <div className="grid grid-cols-1 gap-1 md:grid-cols-2">
                                    {inspectors.map((i) => (
                                        <label
                                            key={i.id}
                                            className="flex items-center gap-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={form.data.inspector_ids.includes(
                                                    i.id,
                                                )}
                                                onChange={() =>
                                                    toggleInspector(i.id)
                                                }
                                            />
                                            {i.name} ({i.email})
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {form.errors.scheduled_start_at && (
                                <p className="text-xs text-destructive">
                                    {form.errors.scheduled_start_at}
                                </p>
                            )}

                            <Button type="submit" disabled={form.processing}>
                                Schedule Inspection
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </>
    );
}
