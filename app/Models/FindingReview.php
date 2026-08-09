<?php

namespace App\Models;

use App\Enums\FindingReviewAction;
use Database\Factories\FindingReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only history of finding lifecycle actions.
 *
 * @property int $id
 * @property int $finding_id
 * @property int|null $corrective_action_id
 * @property int|null $reviewer_id
 * @property FindingReviewAction $action
 * @property string|null $status_before
 * @property string|null $status_after
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Finding $finding
 * @property-read CorrectiveAction|null $correctiveAction
 * @property-read User|null $reviewer
 */
#[Fillable([
    'finding_id',
    'corrective_action_id',
    'reviewer_id',
    'action',
    'status_before',
    'status_after',
    'comment',
])]
class FindingReview extends Model
{
    /** @use HasFactory<FindingReviewFactory> */
    use HasFactory;

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class, 'finding_id');
    }

    /** @return BelongsTo<CorrectiveAction, $this> */
    public function correctiveAction(): BelongsTo
    {
        return $this->belongsTo(CorrectiveAction::class, 'corrective_action_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'action' => FindingReviewAction::class,
        ];
    }
}
