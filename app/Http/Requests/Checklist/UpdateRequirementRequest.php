<?php

namespace App\Http\Requests\Checklist;

use App\Enums\ProgramType;
use App\Enums\RequirementResponseType;
use App\Enums\RequirementSeverity;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistVersion;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for editing a requirement within a DRAFT checklist version.
 */
class UpdateRequirementRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ChecklistVersion $version */
        $version = $this->route('checklist_version');

        return $this->user()?->can('manageRequirements', $version) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var ChecklistVersion $version */
        $version = $this->route('checklist_version');
        /** @var ChecklistRequirement $requirement */
        $requirement = $this->route('requirement');

        return [
            'code' => [
                'sometimes', 'required', 'string', 'max:50',
                Rule::unique('checklist_requirements', 'code')
                    ->where('checklist_version_id', $version->id)
                    ->ignore($requirement->id),
            ],
            'requirement' => ['sometimes', 'required', 'string', 'max:1000'],
            'guidance' => ['nullable', 'string', 'max:2000'],
            'checklist_section_id' => [
                'sometimes', 'required', 'integer',
                Rule::exists('checklist_sections', 'id')->where('checklist_version_id', $version->id),
            ],
            'response_type' => ['sometimes', 'required', 'string', Rule::in(RequirementResponseType::values())],
            'applicable_program_types' => ['nullable', 'array'],
            'applicable_program_types.*' => ['string', Rule::in(ProgramType::values())],
            'severity' => ['sometimes', 'required', 'string', Rule::in(RequirementSeverity::values())],
            'is_mandatory' => ['sometimes', 'boolean'],
            'requires_document' => ['sometimes', 'boolean'],
            'is_reviewer_only' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
