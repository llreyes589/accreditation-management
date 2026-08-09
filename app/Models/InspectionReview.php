<?php

namespace App\Models;

use Database\Factories\InspectionReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only review history for an inspection (returned for revision,
 * completed). Mirrors the audit trail with the before/after status and the
 * reviewer's summary / comment.
 *
 * @property int $id
 * @property int $inspection_id
 * @property int|null $reviewer_id
 * @property string|null $status_before
 * @property string|null $status_after
 * @property string|null $summary
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Inspection $inspection
 * @property-read User|null $reviewer
 */
#[Fillable([
    'inspection_id',
    'reviewer_id',
    'status_before',
    'status_after',
    'summary',
    'comment',
])]
class InspectionReview extends Model
{
    /** @use HasFactory<InspectionReviewFactory> */
    use HasFactory;

    /** @return BelongsTo<Inspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
