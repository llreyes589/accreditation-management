<?php

namespace App\Models;

use App\Enums\AuditAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Append-only audit record.
 *
 * Updates and deletes are blocked at the model level; there is no
 * application code path that mutates an existing audit row.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property int|null $institution_id
 * @property AuditAction $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed>|null $context
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 */
#[Fillable([
    'actor_id',
    'institution_id',
    'action',
    'auditable_type',
    'auditable_id',
    'context',
    'ip_address',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    /** @return BelongsTo<Institution, $this> */
    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    /** @return MorphTo<Model, $this> */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new LogicException('Audit log entries are append-only and cannot be updated.');
        });

        static::deleting(function (): never {
            throw new LogicException('Audit log entries are append-only and cannot be deleted.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => AuditAction::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
