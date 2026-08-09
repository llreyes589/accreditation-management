<?php

namespace App\Models;

use App\Enums\CorrectiveActionStatus;
use Database\Factories\CorrectiveActionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A corrective action assigned against a finding.
 *
 * The institution submits responses and evidence which are appended as immutable
 * {@see CorrectiveActionVersion} rows; the reviewer then accepts, rejects, or
 * reopens. Uploading evidence never auto-closes the action.
 *
 * @property int $id
 * @property int $finding_id
 * @property string $action_reference
 * @property string $title
 * @property string|null $description
 * @property int|null $responsible_user_id
 * @property string|null $responsible_role
 * @property Carbon|null $due_date
 * @property CorrectiveActionStatus|null $status
 * @property Carbon|null $submitted_at
 * @property int|null $submitted_by
 * @property Carbon|null $accepted_at
 * @property int|null $accepted_by
 * @property Carbon|null $rejected_at
 * @property int|null $rejected_by
 * @property string|null $rejection_reason
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Finding $finding
 * @property-read User|null $responsibleUser
 * @property-read User|null $submittedBy
 * @property-read User|null $acceptedBy
 * @property-read User|null $rejectedBy
 * @property-read User|null $closedBy
 * @property-read User|null $creator
 * @property-read HasMany<CorrectiveActionVersion, $this> $versions
 * @property-read HasMany<CorrectiveActionEvidence, $this> $evidence
 */
#[Fillable([
    'finding_id',
    'action_reference',
    'title',
    'description',
    'responsible_user_id',
    'responsible_role',
    'due_date',
    'status',
    'submitted_at',
    'submitted_by',
    'accepted_at',
    'accepted_by',
    'rejected_at',
    'rejected_by',
    'rejection_reason',
    'closed_at',
    'closed_by',
    'created_by',
])]
class CorrectiveAction extends Model
{
    /** @use HasFactory<CorrectiveActionFactory> */
    use HasFactory;

    /** @return BelongsTo<Finding, $this> */
    public function finding(): BelongsTo
    {
        return $this->belongsTo(Finding::class, 'finding_id');
    }

    /** @return BelongsTo<User, $this> */
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    /** @return BelongsTo<User, $this> */
    public function rejectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    /** @return BelongsTo<User, $this> */
    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<CorrectiveActionVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CorrectiveActionVersion::class)->orderBy('version_number');
    }

    /** @return HasMany<CorrectiveActionEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(CorrectiveActionEvidence::class)->orderBy('created_at');
    }

    /**
     * The latest submitted version (immutable submission history).
     */
    public function latestVersion(): ?CorrectiveActionVersion
    {
        return $this->versions()->latest('version_number')->first();
    }

    /**
     * Whether the action is past its due date and not yet resolved/accepted/closed.
     */
    public function isOverdue(?\DateTimeInterface $now = null): bool
    {
        if ($this->due_date === null) {
            return false;
        }

        if (in_array($this->status?->value, ['accepted', 'closed', 'withdrawn'], true)) {
            return false;
        }

        $now ??= now();

        return $this->due_date->lessThan($now);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CorrectiveActionStatus::class,
            'due_date' => 'datetime',
            'submitted_at' => 'datetime',
            'accepted_at' => 'datetime',
            'rejected_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }
}
