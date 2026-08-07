<?php

namespace App\Models;

use App\Enums\ProgramStatus;
use App\Enums\ProgramType;
use Database\Factories\ProgramFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $institution_id
 * @property string $name
 * @property ProgramType $program_type
 * @property ProgramStatus $status
 * @property Carbon|null $accredited_from
 * @property Carbon|null $accredited_until
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Institution $institution
 */
#[Fillable([
    'institution_id',
    'name',
    'program_type',
    'status',
    'accredited_from',
    'accredited_until',
])]
class Program extends Model
{
    /** @use HasFactory<ProgramFactory> */
    use HasFactory;

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return HasMany<AccreditationApplication, $this> */
    public function accreditationApplications(): HasMany
    {
        return $this->hasMany(AccreditationApplication::class);
    }

    /**
     * Whether an accreditation decision is currently in force.
     *
     * Relies solely on the stored validity dates; no duration is assumed.
     */
    public function hasActiveAccreditation(): bool
    {
        if ($this->status !== ProgramStatus::Accredited) {
            return false;
        }

        if ($this->accredited_until === null) {
            return true;
        }

        return $this->accredited_until->endOfDay()->isFuture();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'program_type' => ProgramType::class,
            'status' => ProgramStatus::class,
            'accredited_from' => 'date',
            'accredited_until' => 'date',
        ];
    }
}
