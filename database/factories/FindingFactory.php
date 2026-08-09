<?php

namespace Database\Factories;

use App\Enums\FindingStatus;
use App\Models\Finding;
use App\Models\Inspection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
{
    protected $model = Finding::class;

    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'application_id' => function (array $attrs) {
                $inspection = Inspection::findOrFail($attrs['inspection_id']);
                assert($inspection instanceof Inspection);

                return $inspection->application_id;
            },
            'finding_reference' => 'FND-'.fake()->unique()->numerify('#######'),
            'category' => fake()->randomElement(['major', 'minor', 'observation', 'opportunity_for_improvement']),
            'severity' => fake()->randomElement(['low', 'moderate', 'high', 'critical']),
            'title' => '[SYNTHETIC] Finding '.fake()->sentence(3),
            'description' => '[SYNTHETIC] Description.',
            'source_result' => fake()->randomElement(['compliant', 'noncompliant', 'partially_compliant']),
            'source_section_name' => '[SYNTHETIC] Section',
            'source_requirement_code' => 'REQ-'.fake()->numerify('###'),
            'source_requirement_title' => '[SYNTHETIC] Requirement',
            'source_requirement_description' => '[SYNTHETIC] Requirement description.',
            'source_inspector_notes' => '[SYNTHETIC] Inspector notes.',
            'source_institution_response' => null,
            'status' => FindingStatus::Draft,
            'due_date' => null,
        ];
    }

    public function open(): static
    {
        return $this->state(fn () => ['status' => FindingStatus::Open]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => ['status' => FindingStatus::Resolved]);
    }
}
