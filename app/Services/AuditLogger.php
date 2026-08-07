<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Write-only façade over the append-only audit log.
 *
 * All important actions are recorded through this service so that a future
 * API layer reuses the same write path.
 */
class AuditLogger
{
    public function __construct(private readonly ?Request $request = null) {}

    /**
     * Append an audit entry.
     *
     * @param  array<string, mixed>  $context
     */
    public function log(
        AuditAction $action,
        ?User $actor = null,
        ?Model $subject = null,
        ?int $institutionId = null,
        array $context = [],
    ): AuditLog {
        return AuditLog::create([
            'actor_id' => $actor?->getKey(),
            'institution_id' => $institutionId ?? $this->inferInstitutionId($subject),
            'action' => $action,
            'auditable_type' => $subject !== null ? $subject::class : null,
            'auditable_id' => $subject?->getKey(),
            'context' => $context === [] ? null : $context,
            'ip_address' => $this->request?->ip(),
        ]);
    }

    /**
     * Derive the institution from the subject when it carries an institution_id.
     */
    private function inferInstitutionId(?Model $subject): ?int
    {
        if ($subject === null) {
            return null;
        }

        $value = $subject->getAttribute('institution_id');

        return is_numeric($value) ? (int) $value : null;
    }
}
