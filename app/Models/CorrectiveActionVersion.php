<?php

namespace App\Models;

use Database\Factories\CorrectiveActionVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable record of a single institution submission for a corrective action.
 *
 * Previously submitted versions are never overwritten after review — each
 * submission appends a new version so the reviewer always sees exactly what was
 * submitted at the time.
 *
 * @property int $id
 * @property int $corrective_action_id
 * @property int $version_number
 * @property string $proposed_action
 * @property string|null $implementation_notes
 * @property array<int, array{document_id: int, note: string}>|null $evidence
 * @property int|null $submitted_by
 * @property Carbon|null $submitted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CorrectiveAction $correctiveAction
 * @property-read User|null $submitter
 */
#[Fillable([
    'corrective_action_id',
    'version_number',
    'proposed_action',
    'implementation_notes',
    'evidence',
    'submitted_by',
    'submitted_at',
])]
class CorrectiveActionVersion extends Model
{
    /** @use HasFactory<CorrectiveActionVersionFactory> */
    use HasFactory;

    public $timestamps = true;

    /** @return BelongsTo<CorrectiveAction, $this> */
    public function correctiveAction(): BelongsTo
    {
        return $this->belongsTo(CorrectiveAction::class, 'corrective_action_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'submitted_at' => 'datetime',
        ];
    }
}
