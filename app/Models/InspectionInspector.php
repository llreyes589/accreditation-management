<?php

namespace App\Models;

use Database\Factories\InspectionInspectorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One inspector's assignment to an inspection.
 *
 * Assignment history is preserved: removing or declining an inspector updates
 * the row's status rather than deleting it, so the audit trail survives.
 *
 * @property int $id
 * @property int $inspection_id
 * @property int $user_id
 * @property string $role lead_inspector | inspector | observer
 * @property string $status invited | accepted | declined | removed
 * @property Carbon|null $invited_at
 * @property Carbon|null $accepted_at
 * @property Carbon|null $declined_at
 * @property string|null $decline_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Inspection $inspection
 * @property-read User $user
 */
#[Fillable([
    'inspection_id',
    'user_id',
    'role',
    'status',
    'invited_at',
    'accepted_at',
    'declined_at',
    'decline_reason',
])]
class InspectionInspector extends Model
{
    /** @use HasFactory<InspectionInspectorFactory> */
    use HasFactory;

    /** @return BelongsTo<Inspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function isLead(): bool
    {
        return $this->role === 'lead_inspector';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'invited_at' => 'datetime',
            'accepted_at' => 'datetime',
            'declined_at' => 'datetime',
        ];
    }
}
