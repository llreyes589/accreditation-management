<?php

namespace App\Http\Requests\Checklist;

use App\Models\ChecklistVersion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmation payload for publishing a DRAFT checklist version.
 */
class PublishChecklistVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ChecklistVersion $version */
        $version = $this->route('checklist_version');

        return $this->user()?->can('publish', $version) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
