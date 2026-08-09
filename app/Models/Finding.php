<?php

namespace App\Models;

use App\Enums\FindingStatus;
use Database\Factories\FindingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A finding raised from a completed (or submitted) inspection criterion.
 *
 * The historical context (requirement code/title/description, section name,
 * severity at inspection time, result, inspector notes, institution response) is
 * copied on creation and is immutable — later edits to the live checklist or
 * inspection record never change a finding's context.
 *
 * @property int $id
 * @property int $inspection_id
 * @property int|null $inspection_criterion_id
 * @property int $application_id
 * @property string $finding_reference
 * @property string $category
 * @property string $severity
 * @property string $title
 * @property string|null $description
 * @property string|null $source_result
 * @property string|null $source_section_name
 * @property string|null $source_requirement_code
 * @property string|null $source_requirement_title
 * @property string|null $source_requirement_description
 * @property string|null $source_inspector_notes
 * @property string|null $source_institution_response
 * @property FindingStatus|null $status
 * @property Carbon|null $due_date
 * @property Carbon|null $institution_acknowledged_at
 * @property int|null $acknowledged_by
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property Carbon|null $waived_at
 * @property int|null $waived_by
 * @property Carbon|null $voided_at
 * @property int|null $voided_by
 * @property string|null $void_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Inspection $inspection
 * @property-read AccreditationApplication $application
 * @property-read InspectionCriterion|null $criterion
 * @property-read User|null $acknowledgedBy
 * @property-read User|null $closedBy
 * @property-read User|null $waivedBy
 * @property-read User|null $voidedBy
 * @property-read User|null $creator
 * @property-read HasMany<CorrectiveAction, $this> $correctiveActions
 * @property-read HasMany<FindingReview, $this> $reviews
 */
#[Fillable([
    'inspection_id',
    'inspection_criterion_id',
    'application_id',
    'finding_reference',
    'category',
    'severity',
    'title',
    'description',
    'source_result',
    'source_section_name',
    'source_requirement_code',
    'source_requirement_title',
    'source_requirement_description',
    'source_inspector_notes',
    'source_institution_response',
    'status',
    'due_date',
    'institution_acknowledged_at',
    'acknowledged_by',
    'resolved_at',
    'closed_at',
    'closed_by',
    'waived_at',
    'waived_by',
    'voided_at',
    'voided_by',
    'void_reason',
    'created_by',
])]
class Finding extends Model
{
    /** @use HasFactory<FindingFactory> */
    use HasFactory;

    /** @return BelongsTo<Inspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /** @return BelongsTo<AccreditationApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AccreditationApplication::class, 'application_id');
    }

    /** @return BelongsTo<InspectionCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(InspectionCriterion::class, 'inspection_criterion_id');
    }

    /** @return BelongsTo<User, $this> */
    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function waivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'waived_by');
    }

    /** @return BelongsTo<User, $this> */
    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CorrectiveAction, $this> */
    public function correctiveActions(): HasMany
    {
        return $this->hasMany(CorrectiveAction::class)->orderBy('created_at');
    }

    /** @return HasMany<FindingReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(FindingReview::class)->orderBy('created_at');
    }

    /**
     * The institution that owns the finding (via its application).
     */
    public function institutionId(): ?int
    {
        return $this->application->institution_id;
    }

    /**
     * Whether a corrective action has been accepted (used to gate CLOSED).
     */
    public function hasAcceptedCorrectiveAction(): bool
    {
        return $this->correctiveActions()
            ->where('status', 'accepted')
            ->exists();
    }

    /**
     * Whether the finding is past its due date and still unresolved.
     */
    public function isOverdue(?\DateTimeInterface $now = null): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        if (in_array($this->status?->value, ['resolved', 'closed', 'waived', 'voided'], true)) {
            return false;
        }

        $now ??= now();

        return $this->due_date->lessThan($now);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOverdue(Builder $query, ?\DateTimeInterface $now = null): Builder
    {
        $now ??= now();

        return $query->whereNotNull('due_date')
            ->where('due_date', '<', $now)
            ->whereNotIn('status', ['resolved', 'closed', 'waived', 'voided']);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => FindingStatus::class,
            'due_date' => 'datetime',
            'institution_acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'waived_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }
}
