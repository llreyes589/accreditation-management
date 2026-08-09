<?php

namespace Database\Factories;

use App\Models\CorrectiveAction;
use App\Models\CorrectiveActionVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CorrectiveActionVersion>
 */
class CorrectiveActionVersionFactory extends Factory
{
    protected $model = CorrectiveActionVersion::class;

    public function definition(): array
    {
        return [
            'corrective_action_id' => CorrectiveAction::factory(),
            'version_number' => 1,
            'proposed_action' => '[SYNTHETIC] Proposed corrective action.',
            'implementation_notes' => '[SYNTHETIC] Implementation notes.',
            'evidence' => null,
            'submitted_by' => null,
            'submitted_at' => now(),
        ];
    }
}
