<?php

namespace App\Models;

use App\Enums\ProgramType;
use App\Enums\RequirementResponseType;
use App\Enums\RequirementSeverity;
use Database\Factories\ChecklistRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One configurable compliance requirement inside a version + section.
 *
 * Existing column names are preserved (requirement = title, guidance =
 * description, position = display order); versioning and the response-type /
 * document columns are additive. `applicable_program_types` (null = ALL) holds
 * controlled ProgramType values, never free text.
 *
 * @property int $id
 * @property int $checklist_version_id
 * @property int $checklist_section_id
 * @property string $code
 * @property string $requirement
 * @property string|null $guidance
 * @property RequirementResponseType $response_type
 * @property RequirementSeverity $severity
 * @property int $weight
 * @property int|null $pass_threshold
 * @property bool $is_mandatory
 * @property bool $is_reviewer_only
 * @property bool $requires_document
 * @property array<int, string>|null $applicable_program_types
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ChecklistVersion $version
 * @property-read ChecklistSection|null $section
 */
#[Fillable([
    'checklist_version_id',
    'checklist_section_id',
    'code',
    'requirement',
    'guidance',
    'response_type',
    'severity',
    'weight',
    'pass_threshold',
    'is_mandatory',
    'is_reviewer_only',
    'requires_document',
    'applicable_program_types',
    'position',
    'is_active',
])]
class ChecklistRequirement extends Model
{
    /** @use HasFactory<ChecklistRequirementFactory> */
    use HasFactory;

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeApplicantAnswerable(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->where('is_reviewer_only', false);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position');
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

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'applicable_program_types' => 'array',
            'severity' => RequirementSeverity::class,
            'response_type' => RequirementResponseType::class,
            'is_mandatory' => 'boolean',
            'is_reviewer_only' => 'boolean',
            'requires_document' => 'boolean',
            'is_active' => 'boolean',
            'weight' => 'integer',
            'position' => 'integer',
        ];
    }
}
