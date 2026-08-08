<?php

namespace App\Models;

use App\Enums\ChecklistVersionStatus;
use App\Enums\ProgramType;
use Database\Factories\ChecklistVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;

/**
 * One immutable-once-published edition of the accreditation checklist.
 *
 * @property int $id
 * @property string $version_code
 * @property string $name
 * @property string|null $description
 * @property ProgramType $program_type
 * @property ChecklistVersionStatus $status
 * @property Carbon|null $effective_from
 * @property Carbon|null $effective_until
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property string|null $published_reason
 * @property Carbon|null $retired_at
 * @property int|null $retired_by
 * @property string|null $retired_reason
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read User|null $publisher
 * @property-read User|null $retirer
 * @property-read HasMany<ChecklistSection, $this> $sections
 */
#[Fillable([
    'version_code',
    'name',
    'description',
    'program_type',
    'status',
    'effective_from',
    'effective_until',
    'published_at',
    'published_by',
    'published_reason',
    'retired_at',
    'retired_by',
    'retired_reason',
    'created_by',
])]
class ChecklistVersion extends Model
{
    /** @use HasFactory<ChecklistVersionFactory> */
    use HasFactory;

    public const PROGRAM_TYPES = [ProgramType::AnatomicPathology, ProgramType::ClinicalPathology, ProgramType::CombinedPathology];

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return BelongsTo<User, $this> */
    public function retirer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'retired_by');
    }

    /** @return HasMany<ChecklistSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(ChecklistSection::class);
    }

    /**
     * All requirements across the version's sections.
     *
     * @return HasManyThrough<ChecklistRequirement, ChecklistSection, $this>
     */
    public function requirements(): HasManyThrough
    {
        return $this->hasManyThrough(ChecklistRequirement::class, ChecklistSection::class);
    }

    public function isEditable(): bool
    {
        return $this->status->isEditable();
    }

    public function isPublished(): bool
    {
        return $this->status->isPublished();
    }

    public function isRetired(): bool
    {
        return $this->status->isRetired();
    }

    /**
     * Sections in display order, with their requirements nested in order.
     *
     * @return array<int, array<string, mixed>>
     */
    public function outline(): array
    {
        return $this->sections()
            ->orderBy('position')
            ->get()
            ->map(function (ChecklistSection $section): array {
                return [
                    'id' => $section->id,
                    'code' => $section->code,
                    'title' => $section->title,
                    'description' => $section->description,
                    'display_order' => $section->position,
                    'is_active' => $section->is_active,
                    'requirements' => $section->requirements()
                        ->orderBy('position')
                        ->get()
                        ->map(fn (ChecklistRequirement $r): array => [
                            'id' => $r->id,
                            'code' => $r->code,
                            'title' => $r->requirement,
                            'description' => $r->guidance,
                            'response_type' => $r->response_type->value,
                            'applicable_program_types' => $r->applicable_program_types,
                            'severity' => $r->severity->value,
                            'is_mandatory' => $r->is_mandatory,
                            'requires_document' => $r->requires_document,
                            'display_order' => $r->position,
                            'is_active' => $r->is_active,
                        ])
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', ChecklistVersionStatus::Draft);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', ChecklistVersionStatus::Published);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeRetired(Builder $query): Builder
    {
        return $query->where('status', ChecklistVersionStatus::Retired);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForProgramType(Builder $query, ProgramType $type): Builder
    {
        return $query->where('program_type', $type->value);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ChecklistVersionStatus::class,
            'program_type' => ProgramType::class,
            'effective_from' => 'date',
            'effective_until' => 'date',
            'published_at' => 'datetime',
            'retired_at' => 'datetime',
            'requires_document' => 'boolean',
        ];
    }
}
