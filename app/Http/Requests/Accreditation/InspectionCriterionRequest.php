<?php

namespace App\Http\Requests\Accreditation;

use App\Enums\InspectionResult;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for saving a single inspection criterion.
 */
class InspectionCriterionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $results = implode(',', array_column(InspectionResult::cases(), 'value'));

        return [
            'result' => ['sometimes', 'string', "in:{$results}"],
            'inspector_notes' => ['nullable', 'string', 'max:5000'],
            'institution_response' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
