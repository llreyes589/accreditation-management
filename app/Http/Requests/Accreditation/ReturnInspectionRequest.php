<?php

namespace App\Http\Requests\Accreditation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for returning an inspection for revision (comment required).
 */
class ReturnInspectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReviewInspection() ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'max:5000'],
        ];
    }
}
