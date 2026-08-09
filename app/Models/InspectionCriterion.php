<?php

namespace App\Models;

use App\Enums\InspectionResult;
use Database\Factories\InspectionCriterionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single copied checklist criterion for an inspection (the historical
 * snapshot). See the migration note — this is a point-in-time copy and must not
 * be re-read from the live checklist definition.
 *
 * @property int $id
 * @property int $inspection_id
 * @property int|null $source_requirement_id
 * @property string|null $section_code
 * @property string|null $section_name
 * @property string|null $requirement_code
 * @property string $title
 * @property string|null $description
 * @property string|null $severity
 * @property int $display_order
 * @property string|null $response_type
 * @property InspectionResult|null $result
 * @property string|null $inspector_notes
 * @property string|null $institution_response
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Inspection $inspection
 * @property-read User|null $reviewer
 * @property-read HasMany<InspectionEvidence, $this> $evidence
 */
#[Fillable([
    'inspection_id',
    'source_requirement_id',
    'section_code',
    'section_name',
    'requirement_code',
    'title',
    'description',
    'severity',
    'display_order',
    'response_type',
    'result',
    'inspector_notes',
    'institution_response',
    'reviewed_by',
    'reviewed_at',
])]
class InspectionCriterion extends Model
{
    /** @use HasFactory<InspectionCriterionFactory> */
    use HasFactory;

    /** @return BelongsTo<Inspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<InspectionEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(InspectionEvidence::class, 'inspection_criterion_id');
    }

    public function isStarted(): bool
    {
        return $this->result !== null && $this->result !== InspectionResult::NotStarted;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'result' => InspectionResult::class,
            'display_order' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }
}
