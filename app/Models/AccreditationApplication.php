<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Enums\ApplicationType;
use App\Enums\WizardStep;
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
 * @property int|null $program_id
 * @property ApplicationType $application_type
 * @property string|null $checklist_version
 * @property string $application_reference
 * @property string|null $cycle_label
 * @property ApplicationStatus $status
 * @property WizardStep $current_step
 * @property int $completion_percentage
 * @property array<string, mixed>|null $program_details
 * @property array<string, mixed>|null $institution_information
 * @property array<string, mixed>|null $leadership_staffing
 * @property array<string, mixed>|null $facilities_services
 * @property array<string, mixed>|null $curriculum_training
 * @property Carbon|null $declaration_accepted_at
 * @property int|null $declaration_accepted_by
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $under_review_at
 * @property int|null $under_review_by
 * @property Carbon|null $returned_at
 * @property int|null $returned_by
 * @property string|null $returned_comment
 * @property Carbon|null $completed_for_inspection_at
 * @property int|null $completed_for_inspection_by
 * @property Carbon|null $withdrawn_at
 * @property int|null $withdrawn_by
 * @property int|null $created_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Institution|null $institution
 * @property-read Program|null $program
 */
#[Fillable([
    'institution_id',
    'program_id',
    'application_type',
    'checklist_version',
    'checklist_version_id',
    'application_reference',
    'cycle_label',
    'status',
    'current_step',
    'completion_percentage',
    'program_details',
    'institution_information',
    'leadership_staffing',
    'facilities_services',
    'curriculum_training',
    'declaration_accepted_at',
    'declaration_accepted_by',
    'submitted_by',
    'submitted_at',
    'under_review_by',
    'under_review_at',
    'returned_by',
    'returned_at',
    'returned_comment',
    'completed_for_inspection_by',
    'completed_for_inspection_at',
    'withdrawn_at',
    'withdrawn_by',
    'created_by',
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

    /** @return BelongsTo<ChecklistVersion, $this> */
    public function checklistVersion(): BelongsTo
    {
        return $this->belongsTo(ChecklistVersion::class, 'checklist_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function declarationAcceptor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'declaration_accepted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function withdrawnBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'withdrawn_by');
    }

    /** @return BelongsTo<User, $this> */
    public function underReviewBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'under_review_by');
    }

    /** @return BelongsTo<User, $this> */
    public function returnedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'returned_by');
    }

    /** @return BelongsTo<User, $this> */
    public function completedForInspectionBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_for_inspection_by');
    }

    /** @return HasMany<Inspection, $this> */
    public function inspections(): HasMany
    {
        return $this->hasMany(Inspection::class, 'application_id');
    }

    /** @return HasMany<CompletenessReview, $this> */
    public function completenessReviews(): HasMany
    {
        return $this->hasMany(CompletenessReview::class, 'application_id')->orderBy('created_at');
    }

    /** @return HasMany<ApplicationRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(ApplicationRequirement::class);
    }

    /** @return HasMany<Document, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'application_id');
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
     * Applications still occupying an accreditation cycle for their program.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', ApplicationStatus::activeValues());
    }

    /**
     * Applications waiting for or undergoing reviewer completeness review.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeAwaitingCompletenessReview(Builder $query): Builder
    {
        return $query->whereIn('status', [
            ApplicationStatus::Submitted,
            ApplicationStatus::UnderCompletenessReview,
        ]);
    }

    /**
     * Applications returned to the institution for revision.
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeReturnedForRevision(Builder $query): Builder
    {
        return $query->where('status', ApplicationStatus::ReturnedForRevision);
    }

    /**
     * Whether the applicant may still edit this application.
     */
    public function isEditableByApplicant(): bool
    {
        return $this->status->isEditableByInstitution();
    }

    public function declarationAccepted(): bool
    {
        return $this->declaration_accepted_at !== null;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'application_type' => ApplicationType::class,
            'status' => ApplicationStatus::class,
            'current_step' => WizardStep::class,
            'completion_percentage' => 'integer',
            'checklist_version_id' => 'integer',
            'program_details' => 'array',
            'institution_information' => 'array',
            'leadership_staffing' => 'array',
            'facilities_services' => 'array',
            'curriculum_training' => 'array',
            'declaration_accepted_at' => 'datetime',
            'submitted_at' => 'datetime',
            'under_review_at' => 'datetime',
            'returned_at' => 'datetime',
            'completed_for_inspection_at' => 'datetime',
            'withdrawn_at' => 'datetime',
        ];
    }
}
