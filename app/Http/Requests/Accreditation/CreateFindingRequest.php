<?php

namespace App\Http\Requests\Accreditation;

use Illuminate\Foundation\Http\FormRequest;

class CreateFindingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReviewInspection() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inspection_id' => ['required', 'integer', 'exists:inspections,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['required', 'string', 'in:major,minor,observation,opportunity_for_improvement'],
            'severity' => ['nullable', 'string', 'max:50'],
            'due_date' => ['nullable', 'date'],
            'inspection_criterion_id' => ['nullable', 'integer', 'exists:inspection_criteria,id'],
        ];
    }
}
