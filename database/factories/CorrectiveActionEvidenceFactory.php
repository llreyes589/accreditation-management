<?php

namespace Database\Factories;

use App\Models\CorrectiveAction;
use App\Models\CorrectiveActionEvidence;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrectiveActionEvidence>
 */
class CorrectiveActionEvidenceFactory extends Factory
{
    protected $model = CorrectiveActionEvidence::class;

    public function definition(): array
    {
        return [
            'corrective_action_id' => CorrectiveAction::factory(),
            'document_id' => Document::factory(),
            'description' => '[SYNTHETIC] Evidence note.',
            'uploaded_by' => null,
        ];
    }
}
