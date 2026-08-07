<?php

namespace Database\Factories;

use App\Enums\RequirementResponseStatus;
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
            'status' => RequirementResponseStatus::NotStarted,
            'response' => null,
            'reviewer_comment' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }
}
