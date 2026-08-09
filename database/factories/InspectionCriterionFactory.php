<?php

namespace Database\Factories;

use App\Enums\InspectionResult;
use App\Models\Inspection;
use App\Models\InspectionCriterion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionCriterion>
 */
class InspectionCriterionFactory extends Factory
{
    protected $model = InspectionCriterion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'source_requirement_id' => null,
            'section_code' => 'SEC-1',
            'section_name' => '[SYNTHETIC] Section',
            'requirement_code' => 'REQ-1',
            'title' => '[SYNTHETIC] Requirement',
            'description' => '[SYNTHETIC] Description',
            'severity' => 'core',
            'display_order' => 0,
            'response_type' => 'compliance',
            'result' => InspectionResult::NotStarted,
            'inspector_notes' => null,
            'institution_response' => null,
            'reviewed_by' => null,
            'reviewed_at' => null,
        ];
    }

    public function compliant(): self
    {
        return $this->state(fn (): array => ['result' => InspectionResult::Compliant]);
    }

    public function noncompliant(): self
    {
        return $this->state(fn (): array => [
            'result' => InspectionResult::Noncompliant,
            'inspector_notes' => '[SYNTHETIC] Non-compliant finding.',
        ]);
    }
}
