<?php

namespace App\Http\Requests\Checklist;

use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a section within a DRAFT checklist version.
 */
class UpdateSectionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ChecklistVersion $version */
        $version = $this->route('checklist_version');

        return $this->user()?->can('manageSections', $version) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ChecklistVersion $version */
        $version = $this->route('checklist_version');
        /** @var ChecklistSection $section */
        $section = $this->route('section');

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('checklist_sections', 'code')
                    ->where('checklist_version_id', $version->id)
                    ->ignore($section->id),
            ],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
