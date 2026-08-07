<?php

namespace App\Http\Requests\Accreditation;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for accepting the applicant declaration.
 */
class AcceptDeclarationRequest extends FormRequest
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
        return [
            'declaration_accepted' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'declaration_accepted.required' => 'You must accept the declaration before submitting.',
            'declaration_accepted.accepted' => 'You must accept the declaration before submitting.',
        ];
    }
}
