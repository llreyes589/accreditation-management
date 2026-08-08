<?php

namespace App\Http\Requests\Checklist;

use App\Enums\ProgramType;
use App\Models\ChecklistVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a DRAFT checklist version's metadata.
 *
 * The version_code may be changed but must stay unique among all versions.
 */
class UpdateChecklistVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ChecklistVersion $version */
        $version = $this->route('checklist_version');

        return $this->user()?->can('update', $version) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ChecklistVersion $version */
        $version = $this->route('checklist_version');

        return [
            'version_code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('checklist_versions', 'version_code')->ignore($version->id),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'program_type' => ['sometimes', 'required', 'string', Rule::in(ProgramType::values())],
            'effective_from' => ['nullable', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ];
    }
}
