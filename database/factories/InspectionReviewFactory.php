<?php

namespace Database\Factories;

use App\Models\Inspection;
use App\Models\InspectionReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InspectionReview>
 */
class InspectionReviewFactory extends Factory
{
    protected $model = InspectionReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'inspection_id' => Inspection::factory(),
            'reviewer_id' => User::factory(),
            'status_before' => 'submitted_for_review',
            'status_after' => 'completed',
            'summary' => '[SYNTHETIC] Review summary.',
            'comment' => null,
        ];
    }
}
