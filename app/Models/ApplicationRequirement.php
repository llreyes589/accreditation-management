<?php

namespace App\Models;

use App\Enums\ApplicantResponseStatus;
use App\Enums\ReviewerResponseStatus;
use Database\Factories\ApplicationRequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An institution's response to one configurable checklist requirement.
 *
 * Applicant fields (applicant_*) and reviewer fields (reviewer_*) are kept
 * strictly separate: the applicant's claim is never an acceptance.
 *
 * @property int $id
 * @property int $accreditation_application_id
 * @property int $checklist_requirement_id
 * @property ApplicantResponseStatus|null $applicant_status
 * @property string|null $applicant_response
 * @property string|null $applicant_explanation
 * @property Carbon|null $responded_at
 * @property int|null $responded_by
 * @property ReviewerResponseStatus $reviewer_status
 * @property string|null $reviewer_comment
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccreditationApplication $application
 * @property-read ChecklistRequirement|null $requirement
 */
#[Fillable([
    'accreditation_application_id',
    'checklist_requirement_id',
    'checklist_version_id',
    'applicant_status',
    'applicant_response',
    'applicant_explanation',
    'responded_at',
    'responded_by',
    'reviewer_status',
    'reviewer_comment',
    'reviewed_by',
    'reviewed_at',
])]
class ApplicationRequirement extends Model
{
    /** @use HasFactory<ApplicationRequirementFactory> */
    use HasFactory;

    /**
     * Fields the applicant is ever permitted to write.
     *
     * Used to guarantee that reviewer state cannot be mass-assigned from an
     * applicant-facing request.
     */
    public const APPLICANT_WRITABLE = [
        'applicant_status',
        'applicant_response',
        'applicant_explanation',
        'responded_at',
        'responded_by',
    ];

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

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'application_requirement_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Whether the applicant has answered this requirement sufficiently for
     * submission purposes.
     */
    public function isAnsweredByApplicant(): bool
    {
        if (! $this->applicant_status->satisfiesMandatory()) {
            return false;
        }

        if ($this->applicant_status->requiresExplanation()) {
            return filled($this->applicant_explanation);
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applicant_status' => ApplicantResponseStatus::class,
            'reviewer_status' => ReviewerResponseStatus::class,
            'responded_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }
}
