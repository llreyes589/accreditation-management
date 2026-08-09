<?php

namespace Database\Factories;

use App\Enums\InspectionStatus;
use App\Models\AccreditationApplication;
use App\Models\ChecklistVersion;
use App\Models\Inspection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Inspection>
 */
class InspectionFactory extends Factory
{
    protected $model = Inspection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => AccreditationApplication::factory(),
            'checklist_version_id' => ChecklistVersion::factory(),
            'status' => InspectionStatus::Scheduled,
            'inspection_type' => 'onsite',
            'scheduled_start_at' => now()->addDays(7),
            'scheduled_end_at' => now()->addDays(7)->addHours(2),
            'timezone' => 'Asia/Manila',
            'location' => '[SYNTHETIC] Inspection venue',
            'meeting_url' => null,
            'instructions' => null,
            'created_by' => User::factory(),
            'scheduled_by' => User::factory(),
        ];
    }

    public function scheduled(): self
    {
        return $this->state(fn (): array => ['status' => InspectionStatus::Scheduled]);
    }

    public function inProgress(): self
    {
        return $this->state(fn (): array => [
            'status' => InspectionStatus::InProgress,
            'started_at' => now(),
        ]);
    }

    public function submitted(): self
    {
        return $this->state(fn (): array => [
            'status' => InspectionStatus::SubmittedForReview,
            'started_at' => now()->subHour(),
            'submitted_at' => now(),
        ]);
    }

    public function completed(): self
    {
        return $this->state(fn (): array => [
            'status' => InspectionStatus::Completed,
            'started_at' => now()->subHours(2),
            'submitted_at' => now()->subHour(),
            'completed_at' => now(),
        ]);
    }

    public function cancelled(): self
    {
        return $this->state(fn (): array => [
            'status' => InspectionStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => '[SYNTHETIC] Cancelled for testing.',
        ]);
    }
}
