<?php

namespace Database\Factories;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ReviewerResponseStatus;
use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Models\ChecklistRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApplicationRequirement>
 */
class ApplicationRequirementFactory extends Factory
{
    protected $model = ApplicationRequirement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'accreditation_application_id' => AccreditationApplication::factory(),
            'checklist_requirement_id' => ChecklistRequirement::factory(),
            'applicant_status' => ApplicantResponseStatus::NotStarted,
            'applicant_response' => null,
            'applicant_explanation' => null,
            'responded_at' => null,
            'responded_by' => null,
            'reviewer_status' => ReviewerResponseStatus::Pending,
            'reviewer_comment' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function complete(): self
    {
        return $this->state(fn (): array => [
            'applicant_status' => ApplicantResponseStatus::Complete,
            'applicant_response' => 'Synthetic response for testing.',
            'responded_at' => now(),
        ]);
    }

    public function notApplicable(?string $explanation = 'Not applicable to this program.'): self
    {
        return $this->state(fn (): array => [
            'applicant_status' => ApplicantResponseStatus::NotApplicable,
            'applicant_explanation' => $explanation,
            'responded_at' => now(),
        ]);
    }
}
