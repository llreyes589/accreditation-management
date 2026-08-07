<?php

namespace App\Models;

use App\Enums\ProgramType;
use Database\Factories\ChecklistRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $checklist_section_id
 * @property string $code
 * @property string $requirement
 * @property string|null $guidance
 * @property int $weight
 * @property int|null $pass_threshold
 * @property bool $is_mandatory
 * @property array<int, string>|null $applicable_program_types
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChecklistSection $section
 */
#[Fillable([
    'checklist_section_id',
    'code',
    'requirement',
    'guidance',
    'weight',
    'pass_threshold',
    'is_mandatory',
    'applicable_program_types',
    'position',
    'is_active',
])]
class ChecklistRequirement extends Model
{
    /** @use HasFactory<ChecklistRequirementFactory> */
    use HasFactory;

    /** @return BelongsTo<ChecklistSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(ChecklistSection::class, 'checklist_section_id');
    }

    /** @return HasMany<ApplicationRequirement, $this> */
    public function applicationRequirements(): HasMany
    {
        return $this->hasMany(ApplicationRequirement::class);
    }

    /**
     * A null applicability list means the requirement applies to all types.
     */
    public function appliesTo(ProgramType $type): bool
    {
        if (empty($this->applicable_program_types)) {
            return true;
        }

        return in_array($type->value, $this->applicable_program_types, true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applicable_program_types' => 'array',
            'is_mandatory' => 'boolean',
            'is_active' => 'boolean',
            'weight' => 'integer',
            'position' => 'integer',
        ];
    }
}
