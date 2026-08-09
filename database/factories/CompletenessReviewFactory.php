<?php

namespace Database\Factories;

use App\Models\AccreditationApplication;
use App\Models\CompletenessReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompletenessReview>
 */
class CompletenessReviewFactory extends Factory
{
    protected $model = CompletenessReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => AccreditationApplication::factory(),
            'reviewer_id' => User::factory(),
            'decision' => CompletenessReview::UNDER_REVIEW,
            'reviewer_comment' => null,
            'deficiencies' => null,
        ];
    }

    public function returned(string $comment = '[SYNTHETIC] Returned for revision.'): self
    {
        return $this->state(fn (): array => [
            'decision' => CompletenessReview::RETURNED_FOR_REVISION,
            'reviewer_comment' => $comment,
        ]);
    }

    public function complete(): self
    {
        return $this->state(fn (): array => [
            'decision' => CompletenessReview::COMPLETE_FOR_INSPECTION,
        ]);
    }
}
