<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use Database\Factories\AccreditationApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $institution_id
 * @property int $program_id
 * @property string $reference
 * @property string|null $cycle_label
 * @property ApplicationStatus $status
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Institution $institution
 * @property-read Program $program
 */
#[Fillable([
    'institution_id',
    'program_id',
    'reference',
    'cycle_label',
    'status',
    'submitted_by',
    'submitted_at',
    'notes',
])]
class AccreditationApplication extends Model
{
    /** @use HasFactory<AccreditationApplicationFactory> */
    use HasFactory;

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return BelongsTo<Program, $this> */
    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return HasMany<ApplicationRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(ApplicationRequirement::class);
    }

    /**
     * Constrain a query to a single institution.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForInstitution(Builder $query, int $institutionId): Builder
    {
        return $query->where('institution_id', $institutionId);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApplicationStatus::class,
            'submitted_at' => 'datetime',
        ];
    }
}
