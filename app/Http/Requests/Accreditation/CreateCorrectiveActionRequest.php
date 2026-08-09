<?php

namespace App\Http\Requests\Accreditation;

use Illuminate\Foundation\Http\FormRequest;

class CreateCorrectiveActionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // policy enforced in controller/service
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'responsible_role' => ['nullable', 'string', 'in:institution_administrator,training_officer,consultant'],
            'due_date' => ['required', 'date'],
        ];
    }
}
