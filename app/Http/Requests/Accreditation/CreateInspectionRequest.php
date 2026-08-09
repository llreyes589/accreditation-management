<?php

namespace App\Http\Requests\Accreditation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for scheduling a new inspection.
 */
class CreateInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canScheduleInspection() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'application_id' => ['required', 'integer', 'exists:accreditation_applications,id'],
            'inspection_type' => ['required', 'in:onsite,virtual'],
            'scheduled_start_at' => ['required', 'date', 'after:now'],
            'scheduled_end_at' => ['required', 'date', 'after:scheduled_start_at'],
            'timezone' => ['sometimes', 'string', 'max:64'],
            'location' => ['nullable', 'string', 'max:1000'],
            'meeting_url' => ['nullable', 'url', 'max:2000'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'lead_inspector_id' => ['required', 'integer', 'exists:users,id'],
            'inspector_ids' => ['sometimes', 'array'],
            'inspector_ids.*' => ['integer', 'exists:users,id'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'scheduled_start_at.after' => 'The scheduled start time must be in the future.',
            'scheduled_end_at.after' => 'The end time must be after the start time.',
            'lead_inspector_id.required' => 'A lead inspector is required.',
            'meeting_url.url' => 'Provide a valid meeting URL.',
        ];
    }
}
