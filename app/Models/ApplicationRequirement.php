<?php

namespace App\Models;

use App\Enums\RequirementResponseStatus;
use Database\Factories\ApplicationRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An institution's response to one configurable checklist requirement,
 * recorded against a single accreditation application.
 *
 * @property int $id
 * @property int $accreditation_application_id
 * @property int $checklist_requirement_id
 * @property RequirementResponseStatus $status
 * @property string|null $response
 * @property string|null $reviewer_comment
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccreditationApplication $application
 * @property-read ChecklistRequirement $requirement
 */
#[Fillable([
    'accreditation_application_id',
    'checklist_requirement_id',
    'status',
    'response',
    'reviewer_comment',
    'reviewed_by',
    'reviewed_at',
])]
class ApplicationRequirement extends Model
{
    /** @use HasFactory<ApplicationRequirementFactory> */
    use HasFactory;

    /** @return BelongsTo<AccreditationApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AccreditationApplication::class, 'accreditation_application_id');
    }

    /** @return BelongsTo<ChecklistRequirement, $this> */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(ChecklistRequirement::class, 'checklist_requirement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequirementResponseStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
