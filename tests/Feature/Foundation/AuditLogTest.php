<?php

namespace Tests\Feature\Foundation;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\Institution;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_an_audit_entry_with_actor_and_subject(): void
    {
        $actor = User::factory()->create();
        $institution = Institution::factory()->create();

        $entry = app(AuditLogger::class)->log(
            AuditAction::Created,
            $actor,
            $institution,
            $institution->id,
            ['note' => 'seeded'],
        );

        $this->assertDatabaseHas('audit_logs', [
            'id' => $entry->id,
            'actor_id' => $actor->id,
            'institution_id' => $institution->id,
            'action' => AuditAction::Created->value,
            'auditable_type' => Institution::class,
            'auditable_id' => $institution->id,
        ]);
    }

    public function test_audit_entries_cannot_be_updated(): void
    {
        $entry = AuditLog::create(['action' => AuditAction::Created]);

        $this->expectException(LogicException::class);

        $entry->update(['action' => AuditAction::Updated]);
    }

    public function test_audit_entries_cannot_be_deleted(): void
    {
        $entry = AuditLog::create(['action' => AuditAction::Created]);

        $this->expectException(LogicException::class);

        $entry->delete();
    }
}
