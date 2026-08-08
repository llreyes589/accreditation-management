<?php

namespace App\Models;

use Database\Factories\ChecklistSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A grouping within a checklist version.
 *
 * Belongs to exactly one ChecklistVersion; its `position` doubles as the
 * display order. Requirements are ordered by their own `position`.
 *
 * @property int $id
 * @property int $checklist_version_id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChecklistVersion $version
 */
#[Fillable([
    'checklist_version_id',
    'code',
    'title',
    'description',
    'position',
    'is_active',
])]
class ChecklistSection extends Model
{
    /** @use HasFactory<ChecklistSectionFactory> */
    use HasFactory;

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

    /** @return HasMany<ChecklistRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(ChecklistRequirement::class, 'checklist_section_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
    }
}
