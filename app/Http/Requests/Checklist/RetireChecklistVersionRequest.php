<?php

namespace App\Http\Requests\Checklist;

use App\Models\ChecklistVersion;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Confirmation payload for retiring a PUBLISHED checklist version.
 */
class RetireChecklistVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var ChecklistVersion $version */
        $version = $this->route('checklist_version');

        return $this->user()?->can('retire', $version) ?? false;
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
