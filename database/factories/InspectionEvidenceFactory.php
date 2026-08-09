<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Inspection;
use App\Models\InspectionCriterion;
use App\Models\InspectionEvidence;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionEvidence>
 */
class InspectionEvidenceFactory extends Factory
{
    protected $model = InspectionEvidence::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'inspection_criterion_id' => InspectionCriterion::factory(),
            'document_id' => Document::factory(),
            'note' => '[SYNTHETIC] Evidence note.',
            'created_by' => User::factory(),
        ];
    }
}
