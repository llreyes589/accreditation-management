<?php

namespace Database\Factories;

use App\Enums\FindingReviewAction;
use App\Models\Finding;
use App\Models\FindingReview;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FindingReview>
 */
class FindingReviewFactory extends Factory
{
    protected $model = FindingReview::class;

    public function definition(): array
    {
        return [
            'finding_id' => Finding::factory(),
            'corrective_action_id' => null,
            'reviewer_id' => null,
            'action' => FindingReviewAction::Created,
            'status_before' => 'draft',
            'status_after' => 'open',
            'comment' => '[SYNTHETIC] Review entry.',
        ];
    }
}
