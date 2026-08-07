<?php

namespace App\Http\Requests\Accreditation;

use App\Enums\ApplicantResponseStatus;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Server-side validation for a single checklist response.
 *
 * Only applicant-side fields are accepted. Any attempt to supply reviewer
 * state is simply not part of the rule set and is discarded.
 */
class SaveChecklistResponseRequest extends FormRequest
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
            'requirement_id' => ['required', 'integer', 'exists:application_requirements,id'],
            'applicant_status' => [
                'required',
                'string',
                Rule::in(ApplicantResponseStatus::selectableValues()),
            ],
            'applicant_response' => ['nullable', 'string', 'max:5000'],
            'applicant_explanation' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $status = ApplicantResponseStatus::tryFrom((string) $this->input('applicant_status'));

            // An explanation is mandatory whenever NOT_APPLICABLE is chosen.
            if ($status?->requiresExplanation() && blank($this->input('applicant_explanation'))) {
                $validator->errors()->add(
                    'applicant_explanation',
                    'An explanation is required when a requirement is marked not applicable.',
                );
            }

            $row = $this->requirementRow();
            $application = $this->routeApplication();

            if ($row === null || $application === null) {
                return;
            }

            // Guard against pointing a valid row id at another application.
            if ((int) $row->accreditation_application_id !== $application->getKey()) {
                $validator->errors()->add(
                    'requirement_id',
                    'That requirement does not belong to this application.',
                );

                return;
            }

            // Reviewer-only requirements are never answerable by the applicant.
            if ($row->requirement?->is_reviewer_only) {
                $validator->errors()->add(
                    'requirement_id',
                    'This requirement is completed by the PSP reviewer and cannot be answered here.',
                );
            }
        });
    }

    public function requirementRow(): ?ApplicationRequirement
    {
        $id = $this->input('requirement_id');

        if (! is_numeric($id)) {
            return null;
        }

        /** @var ApplicationRequirement|null $row */
        $row = ApplicationRequirement::with('requirement')->find((int) $id);

        return $row;
    }

    /**
     * The route-bound application, or null when binding has not resolved one.
     */
    public function routeApplication(): ?AccreditationApplication
    {
        $application = $this->route('application');

        return $application instanceof AccreditationApplication ? $application : null;
    }

    public function applicantStatus(): ApplicantResponseStatus
    {
        return ApplicantResponseStatus::from($this->string('applicant_status')->value());
    }
}
