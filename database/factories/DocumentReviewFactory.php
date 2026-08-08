<?php

namespace Database\Factories;

use App\Enums\DocumentDecision;
use App\Models\Document;
use App\Models\DocumentReview;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentReview>
 */
class DocumentReviewFactory extends Factory
{
    protected $model = DocumentReview::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'document_version_id' => DocumentVersion::factory(),
            'reviewer_id' => User::factory(),
            'decision' => DocumentDecision::Accepted,
            'comment' => null,
            'reviewed_at' => now(),
        ];
    }

    public function rejected(): self
    {
        return $this->state(fn (): array => [
            'decision' => DocumentDecision::Rejected,
            'comment' => fake()->sentence(),
        ]);
    }
}
