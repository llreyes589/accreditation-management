<?php

namespace App\Http\Requests\Checklist;

use App\Enums\ProgramType;
use App\Models\ChecklistVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a new DRAFT checklist version.
 */
class StoreChecklistVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', ChecklistVersion::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'version_code' => ['required', 'string', 'max:50', Rule::unique('checklist_versions', 'version_code')],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'program_type' => ['required', 'string', Rule::in(ProgramType::values())],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
