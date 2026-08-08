<?php

namespace App\Models;

use App\Enums\DocumentStatus;
use App\Enums\DocumentType;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One logical supporting-document submission for an accreditation application.
 *
 * A document may accumulate several immutable {@see DocumentVersion} rows
 * (initial upload + replacements). Review decisions are recorded separately
 * in {@see DocumentReview} so the review history is preserved even after a
 * replacement supersedes an earlier version.
 *
 * @property int $id
 * @property int $application_id
 * @property int|null $application_requirement_id
 * @property DocumentType $document_type
 * @property DocumentStatus $status
 * @property int|null $current_version_id
 * @property int|null $requested_by
 * @property Carbon|null $requested_at
 * @property Carbon|null $last_reviewed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AccreditationApplication $application
 * @property-read ApplicationRequirement|null $applicationRequirement
 * @property-read DocumentVersion|null $currentVersion
 * @property-read DocumentVersion|null $latestVersion
 * @property-read User|null $creator
 * @property-read User|null $requester
 * @property-read Collection<int, DocumentVersion> $versions
 * @property-read Collection<int, DocumentReview> $reviews
 */
#[Fillable([
    'application_id',
    'application_requirement_id',
    'document_type',
    'status',
    'current_version_id',
    'requested_by',
    'requested_at',
    'last_reviewed_at',
    'created_by',
])]
class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    /** @return BelongsTo<AccreditationApplication, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(AccreditationApplication::class, 'application_id');
    }

    /** @return BelongsTo<ApplicationRequirement, $this> */
    public function applicationRequirement(): BelongsTo
    {
        return $this->belongsTo(ApplicationRequirement::class, 'application_requirement_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /** @return HasMany<DocumentVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderBy('version_number');
    }

    /** @return HasMany<DocumentReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class)->orderBy('reviewed_at');
    }

    public function latestVersion(): ?DocumentVersion
    {
        return $this->versions()->latest('version_number')->first();
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForApplication(Builder $query, int $applicationId): Builder
    {
        return $query->where('application_id', $applicationId);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNotIn('status', [DocumentStatus::Withdrawn, DocumentStatus::Superseded]);
    }

    public function isAccepted(): bool
    {
        return $this->status === DocumentStatus::Accepted;
    }

    public function isWithdrawn(): bool
    {
        return $this->status === DocumentStatus::Withdrawn;
    }

    public function isSuperseded(): bool
    {
        return $this->status === DocumentStatus::Superseded;
    }

    /**
     * Whether a reviewer may act on the document in its current state.
     */
    public function isReviewable(): bool
    {
        return $this->status->isReviewable();
    }

    /**
     * A replacement may be uploaded only from a rejected or replacement-requested
     * document.
     */
    public function acceptsReplacement(): bool
    {
        return in_array($this->status, [
            DocumentStatus::Rejected,
            DocumentStatus::ReplacementRequested,
        ], true);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'status' => DocumentStatus::class,
            'requested_at' => 'datetime',
            'last_reviewed_at' => 'datetime',
            'current_version_id' => 'integer',
        ];
    }
}
