<?php

namespace App\Models;

use Database\Factories\CompletenessReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One append-only row in the completeness-review history.
 *
 * Every reviewer action (begin review, return for revision, mark complete) and
 * each applicant resubmission appends a row. The deficiency snapshot is captured
 * as JSON at decision time so a completeness report reflects the state that was
 * reviewed, even after the applicant later makes changes.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $reviewer_id
 * @property string $decision
 * @property string|null $reviewer_comment
 * @property array<int, array<string, string>>|null $deficiencies
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccreditationApplication $application
 * @property-read User|null $reviewer
 */
#[Fillable([
    'application_id',
    'reviewer_id',
    'decision',
    'reviewer_comment',
    'deficiencies',
])]
class CompletenessReview extends Model
{
    /** @use HasFactory<CompletenessReviewFactory> */
    use HasFactory;

    public const UNDER_REVIEW = 'under_review';

    public const RETURNED_FOR_REVISION = 'returned_for_revision';

    public const COMPLETE_FOR_INSPECTION = 'complete_for_inspection';

    public const RESUBMITTED = 'resubmitted';

    /** @return BelongsTo<AccreditationApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AccreditationApplication::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForApplication(Builder $query, int $applicationId): Builder
    {
        return $query->where('application_id', $applicationId);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'reviewer_id' => 'integer',
            'deficiencies' => 'array',
        ];
    }
}
