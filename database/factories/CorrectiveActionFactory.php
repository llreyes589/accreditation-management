<?php

namespace Database\Factories;

use App\Enums\CorrectiveActionStatus;
use App\Models\CorrectiveAction;
use App\Models\Finding;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CorrectiveAction>
 */
class CorrectiveActionFactory extends Factory
{
    protected $model = CorrectiveAction::class;

    public function definition(): array
    {
        return [
            'finding_id' => Finding::factory(),
            'action_reference' => 'CA-'.Str::upper(Str::random(6)),
            'title' => '[SYNTHETIC] Corrective action '.Str::random(5),
            'description' => '[SYNTHETIC] Description.',
            'responsible_user_id' => null,
            'responsible_role' => 'training_officer',
            'due_date' => now()->addDays(14),
            'status' => CorrectiveActionStatus::Open,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn () => ['status' => CorrectiveActionStatus::Submitted, 'submitted_at' => now()]);
    }

    public function accepted(): static
    {
        return $this->state(fn () => ['status' => CorrectiveActionStatus::Accepted, 'submitted_at' => now(), 'accepted_at' => now()]);
    }
}
