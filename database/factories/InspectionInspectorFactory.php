<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionInspector;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionInspector>
 */
class InspectionInspectorFactory extends Factory
{
    protected $model = InspectionInspector::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'user_id' => User::factory(),
            'role' => 'inspector',
            'status' => 'invited',
            'invited_at' => now(),
            'accepted_at' => null,
            'declined_at' => null,
            'decline_reason' => null,
        ];
    }

    public function lead(): self
    {
        return $this->state(fn (): array => ['role' => 'lead_inspector']);
    }

    public function accepted(): self
    {
        return $this->state(fn (): array => [
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }
}
