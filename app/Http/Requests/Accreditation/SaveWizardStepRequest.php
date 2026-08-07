<?php

namespace App\Http\Requests\Accreditation;

use App\Enums\WizardStep;
use App\Services\Accreditation\ApplicationProgressCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Server-side validation for saving one wizard step.
 *
 * Rules are declared per step so the same endpoint validates each section
 * authoritatively, independent of any client-side checks.
 */
class SaveWizardStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('application');

        return $this->user()?->can('update', $application) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $base = [
            'step' => ['required', 'string', Rule::enum(WizardStep::class)],
            'advance' => ['sometimes', 'boolean'],
        ];

        return array_merge($base, $this->rulesForStep($this->resolveStep()));
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesForStep(WizardStep $step): array
    {
        return match ($step) {
            WizardStep::ApplicationDetails => [
                'payload.program_head' => ['required', 'string', 'max:255'],
                'payload.training_duration_years' => ['required', 'integer', 'min:1', 'max:10'],
                'payload.approved_resident_slots' => ['required', 'integer', 'min:1', 'max:500'],
                'payload.program_description' => ['nullable', 'string', 'max:5000'],
            ],
            WizardStep::InstitutionInformation => [
                'payload.bed_capacity' => ['required', 'integer', 'min:0', 'max:10000'],
                'payload.ownership_type' => ['required', 'string', 'max:100'],
                'payload.accrediting_bodies' => ['nullable', 'string', 'max:2000'],
                'payload.institution_notes' => ['nullable', 'string', 'max:5000'],
            ],
            WizardStep::LeadershipStaffing => [
                'payload.program_director' => ['required', 'string', 'max:255'],
                'payload.consultant_count' => ['required', 'integer', 'min:0', 'max:1000'],
                'payload.support_staff_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
                'payload.staffing_notes' => ['nullable', 'string', 'max:5000'],
            ],
            WizardStep::FacilitiesServices => [
                'payload.laboratory_sections' => ['required', 'string', 'max:2000'],
                'payload.annual_specimen_volume' => ['required', 'integer', 'min:0', 'max:10000000'],
                'payload.equipment_summary' => ['nullable', 'string', 'max:5000'],
            ],
            WizardStep::CurriculumTraining => [
                'payload.curriculum_summary' => ['required', 'string', 'max:5000'],
                'payload.rotation_schedule' => ['required', 'string', 'max:5000'],
                'payload.evaluation_methods' => ['nullable', 'string', 'max:5000'],
            ],
            default => [],
        };
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $labels = [];

        foreach (ApplicationProgressCalculator::REQUIRED_FIELDS as $fields) {
            foreach ($fields as $field) {
                $labels['payload.'.$field] = str_replace('_', ' ', $field);
            }
        }

        return $labels;
    }

    public function resolveStep(): WizardStep
    {
        return WizardStep::tryFrom((string) $this->input('step')) ?? WizardStep::first();
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $this->validated()['payload'] ?? [];

        return $payload;
    }

    public function shouldAdvance(): bool
    {
        return $this->boolean('advance');
    }
}
