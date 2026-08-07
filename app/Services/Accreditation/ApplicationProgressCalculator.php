<?php

namespace App\Services\Accreditation;

use App\Enums\ApplicantResponseStatus;
use App\Enums\WizardStep;
use App\Models\AccreditationApplication;

/**
 * Computes wizard progress strictly from persisted application data.
 *
 * No session or client state is consulted: reloading the page or resuming a
 * draft on another device yields the same result.
 */
class ApplicationProgressCalculator
{
    /**
     * Required keys per free-form wizard section.
     *
     * These drive both the progress percentage and the "required fields
     * remaining" display. Server-side request validation enforces the same
     * keys on save.
     *
     * @var array<string, array<int, string>>
     */
    public const REQUIRED_FIELDS = [
        'program_details' => [
            'program_head',
            'training_duration_years',
            'approved_resident_slots',
        ],
        'institution_information' => [
            'bed_capacity',
            'ownership_type',
        ],
        'leadership_staffing' => [
            'program_director',
            'consultant_count',
        ],
        'facilities_services' => [
            'laboratory_sections',
            'annual_specimen_volume',
        ],
        'curriculum_training' => [
            'curriculum_summary',
            'rotation_schedule',
        ],
    ];

    /**
     * Whether a single step is satisfied by the persisted record.
     */
    public function isStepComplete(AccreditationApplication $application, WizardStep $step): bool
    {
        return match ($step) {
            WizardStep::ProgramSelection => $application->program_id !== null,
            WizardStep::ChecklistResponses => $this->checklistComplete($application),
            WizardStep::ReviewDeclaration => $application->declarationAccepted(),
            WizardStep::SubmitApplication => $application->submitted_at !== null,
            default => $this->payloadComplete($application, $step),
        };
    }

    /**
     * Field keys still missing for a step.
     *
     * @return array<int, string>
     */
    public function missingFields(AccreditationApplication $application, WizardStep $step): array
    {
        $column = $step->payloadColumn();

        if ($column === null) {
            return [];
        }

        $payload = $application->getAttribute($column) ?? [];
        $missing = [];

        foreach (self::REQUIRED_FIELDS[$column] ?? [] as $field) {
            if (blank($payload[$field] ?? null)) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Percentage of completable steps satisfied, 0-100.
     */
    public function percentage(AccreditationApplication $application): int
    {
        $steps = WizardStep::completableSteps();
        $total = count($steps);

        if ($total === 0) {
            return 0;
        }

        $complete = count(array_filter(
            $steps,
            fn (WizardStep $step): bool => $this->isStepComplete($application, $step),
        ));

        return (int) round(($complete / $total) * 100);
    }

    /**
     * Full progress breakdown for the UI.
     *
     * @return array<string, mixed>
     */
    public function summary(AccreditationApplication $application): array
    {
        $steps = [];
        $remaining = [];

        foreach (WizardStep::cases() as $step) {
            $isComplete = $this->isStepComplete($application, $step);
            $missing = $this->missingFields($application, $step);

            $steps[] = [
                'key' => $step->value,
                'label' => $step->label(),
                'position' => $step->position(),
                'is_complete' => $isComplete,
                'is_current' => $application->current_step === $step,
                'missing_fields' => $missing,
            ];

            foreach ($missing as $field) {
                $remaining[] = $step->label().': '.$this->humanize($field);
            }
        }

        if (! $this->checklistComplete($application)) {
            $remaining[] = WizardStep::ChecklistResponses->label().': '
                .$this->outstandingMandatoryCount($application).' mandatory response(s) outstanding';
        }

        if (! $application->declarationAccepted()) {
            $remaining[] = WizardStep::ReviewDeclaration->label().': declaration not yet accepted';
        }

        return [
            'steps' => $steps,
            'completion_percentage' => $this->percentage($application),
            'required_fields_remaining' => $remaining,
            'current_step' => $application->current_step->value,
            'status' => $application->status->value,
            'status_label' => $application->status->label(),
        ];
    }

    /**
     * Every applicable mandatory checklist requirement has a usable answer.
     */
    public function checklistComplete(AccreditationApplication $application): bool
    {
        return $this->outstandingMandatoryCount($application) === 0
            && $application->requirements()->exists();
    }

    /**
     * How many mandatory requirements still lack a valid applicant answer.
     */
    public function outstandingMandatoryCount(AccreditationApplication $application): int
    {
        $requirements = $application->requirements()
            ->with('requirement')
            ->get()
            ->filter(fn ($row): bool => (bool) $row->requirement?->is_mandatory);

        return $requirements
            ->reject(fn ($row): bool => $this->isSatisfied($row))
            ->count();
    }

    private function isSatisfied(mixed $row): bool
    {
        $status = $row->applicant_status;

        if (! $status instanceof ApplicantResponseStatus || ! $status->satisfiesMandatory()) {
            return false;
        }

        return $status->requiresExplanation()
            ? filled($row->applicant_explanation)
            : true;
    }

    private function payloadComplete(AccreditationApplication $application, WizardStep $step): bool
    {
        $column = $step->payloadColumn();

        if ($column === null) {
            return false;
        }

        return $this->missingFields($application, $step) === [];
    }

    private function humanize(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
