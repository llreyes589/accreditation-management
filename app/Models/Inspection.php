<?php

namespace App\Models;

use App\Enums\InspectionStatus;
use App\Services\Accreditation\InspectionService;
use Database\Factories\InspectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A scheduled inspection against an accreditation application.
 *
 * The inspection carries its own snapshot of the applicable checklist
 * requirements (see {@see InspectionCriterion}) so the record is immutable
 * against later checklist edits. Status transitions are enforced by
 * {@see InspectionService}.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $checklist_version_id
 * @property InspectionStatus|null $status
 * @property string $inspection_type
 * @property Carbon|null $scheduled_start_at
 * @property Carbon|null $scheduled_end_at
 * @property string $timezone
 * @property string|null $location
 * @property string|null $meeting_url
 * @property string|null $instructions
 * @property int|null $created_by
 * @property int|null $scheduled_by
 * @property Carbon|null $started_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $cancelled_at
 * @property string|null $cancellation_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccreditationApplication $application
 * @property-read ChecklistVersion|null $checklistVersion
 * @property-read User|null $creator
 * @property-read User|null $scheduler
 * @property-read HasMany<InspectionInspector, $this> $inspectors
 * @property-read HasMany<InspectionCriterion, $this> $criteria
 * @property-read HasMany<InspectionEvidence, $this> $evidence
 * @property-read HasMany<InspectionReview, $this> $reviews
 */
#[Fillable([
    'application_id',
    'checklist_version_id',
    'status',
    'inspection_type',
    'scheduled_start_at',
    'scheduled_end_at',
    'timezone',
    'location',
    'meeting_url',
    'instructions',
    'created_by',
    'scheduled_by',
    'started_at',
    'submitted_at',
    'completed_at',
    'cancelled_at',
    'cancellation_reason',
])]
class Inspection extends Model
{
    /** @use HasFactory<InspectionFactory> */
    use HasFactory;

    /** @return BelongsTo<AccreditationApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AccreditationApplication::class, 'application_id');
    }

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function checklistVersion(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function scheduler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scheduled_by');
    }

    /** @return HasMany<InspectionInspector, $this> */
    public function inspectors(): HasMany
    {
        return $this->hasMany(InspectionInspector::class);
    }

    /** @return HasMany<InspectionCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(InspectionCriterion::class)->orderBy('display_order');
    }

    /** @return HasMany<InspectionEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(InspectionEvidence::class);
    }

    /** @return HasMany<InspectionReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(InspectionReview::class)->orderBy('created_at');
    }

    public function leadInspector(): ?InspectionInspector
    {
        return $this->inspectors()
            ->where('role', 'lead_inspector')
            ->where('status', 'accepted')
            ->first();
    }

    /**
     * Whether the given user is an accepted inspector on this inspection.
     */
    public function hasInspector(User $user): bool
    {
        return $this->inspectors()
            ->where('user_id', $user->id)
            ->whereIn('status', ['accepted', 'invited'])
            ->exists();
    }

    /**
     * Applications may carry only one active inspection at a time.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', ['completed', 'cancelled']);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => InspectionStatus::class,
            'scheduled_start_at' => 'datetime',
            'scheduled_end_at' => 'datetime',
            'started_at' => 'datetime',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
