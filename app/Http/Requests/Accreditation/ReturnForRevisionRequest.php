<?php

namespace App\Http\Requests\Accreditation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for returning an application for revision.
 *
 * The reviewer comment is mandatory; the service enforces the same rule, but
 * the request fails fast with a field-level error.
 */
class ReturnForRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canReviewCompleteness() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
        ];
    }

    public function messages(): array
    {
        return [
            'comment.required' => 'A reviewer comment is required when returning an application for revision.',
        ];
    }
}
