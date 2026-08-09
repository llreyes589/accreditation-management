<?php

namespace App\Http\Requests\Accreditation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for completing an inspection (reviewer summary required).
 */
class CompleteInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReviewInspection() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'summary' => ['required', 'string', 'max:5000'],
        ];
    }
}
