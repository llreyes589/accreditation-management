<?php

namespace Tests\Feature\Accreditation\Admin;

use App\Enums\AuditAction;
use App\Enums\ProgramType;
use App\Enums\RoleName;
use App\Models\AccreditationApplication;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use App\Models\Institution;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesFoundationData;
use Tests\Concerns\CreatesWizardData;
use Tests\TestCase;

/**
 * Configurable Checklist Administration — spec scenarios 1-20.
 *
 * All data is synthetic; no real PSP criteria are encoded.
 */
class ChecklistAdministrationTest extends TestCase
{
    use CreatesFoundationData;
    use CreatesWizardData;
    use RefreshDatabase;

    /* 1. PSP administrator can create a draft checklist version. */
    public function test_psp_administrator_can_create_draft_version(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);

        $response = $this->actingAs($admin)->post(route('admin.checklists.store'), [
            'version_code' => '2026.2',
            'name' => 'Combined Pathology Checklist',
            'program_type' => ProgramType::CombinedPathology->value,
            'effective_from' => '2026-01-01',
        ]);

        $response->assertRedirect();

        $created = ChecklistVersion::where('version_code', '2026.2')->firstOrFail();
        $this->assertNotNull($created);
        $response->assertRedirect(route('admin.checklists.edit', $created));
        $this->assertDatabaseHas('checklist_versions', [
            'version_code' => '2026.2',
            'status' => 'draft',
            'created_by' => $admin->id,
        ]);
    }

    /* 2. Authorized CART reviewer can edit a draft version. */
    public function test_cart_reviewer_can_edit_draft_version(): void
    {
        $reviewer = $this->userWithRole(RoleName::CartReviewer);
        $draft = $this->draftChecklist()['version'];

        $this->assertTrue($reviewer->can('update', $draft));

        $response = $this->actingAs($reviewer)->patch(route('admin.checklists.update', $draft), [
            'version_code' => 'EDITED',
            'name' => 'Edited Name',
            'program_type' => ProgramType::CombinedPathology->value,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('checklist_versions', ['id' => $draft->id, 'name' => 'Edited Name']);
    }

    /* 3. Unauthorized institution user cannot access checklist administration. */
    public function test_institution_user_cannot_access_checklist_administration(): void
    {
        $admin = $this->userWithRole(RoleName::InstitutionAdministrator, Institution::factory()->create());

        $this->actingAs($admin)->get(route('admin.checklists.index'))->assertForbidden();
    }

    /* 4. Resident cannot access checklist administration. */
    public function test_resident_cannot_access_checklist_administration(): void
    {
        $resident = $this->userWithRole(RoleName::Resident, Institution::factory()->create());

        $this->actingAs($resident)->get(route('admin.checklists.index'))->assertForbidden();
    }

    /* 5. Requirement codes are unique within a version. */
    public function test_requirement_codes_are_unique_within_a_version(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $data = $this->draftChecklist();
        $version = $data['version'];
        $section = $data['section'];

        $payload = [
            'checklist_section_id' => $section->id,
            'code' => $data['requirement']->code, // duplicate of existing REQ-1
            'requirement' => 'Duplicate code requirement.',
            'response_type' => 'text',
            'severity' => 'minor',
        ];

        $this->actingAs($admin)
            ->post(route('admin.checklists.requirements.store', $version), $payload)
            ->assertSessionHasErrors('code');
    }

    /* 6. A draft version can be edited. */
    public function test_draft_version_can_be_edited(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $draft = $this->draftChecklist()['version'];

        $this->actingAs($admin)
            ->patch(route('admin.checklists.update', $draft), [
                'version_code' => 'DRAFT-X',
                'name' => 'Renamed',
                'program_type' => ProgramType::CombinedPathology->value,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('checklist_versions', ['id' => $draft->id, 'name' => 'Renamed']);
    }

    /* 7. A published version cannot be edited. */
    public function test_published_version_cannot_be_edited(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = ChecklistVersion::factory()->published()->create([
            'version_code' => 'PUB-1',
            'program_type' => ProgramType::CombinedPathology,
        ]);

        $this->assertFalse($admin->can('update', $version));

        $this->actingAs($admin)
            ->patch(route('admin.checklists.update', $version), [
                'version_code' => 'NOPE',
                'name' => 'Hacked',
                'program_type' => ProgramType::CombinedPathology->value,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('checklist_versions', ['id' => $version->id, 'name' => $version->name]);
    }

    /* 8. A retired version remains readable. */
    public function test_retired_version_remains_readable(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = ChecklistVersion::factory()->create([
            'version_code' => 'RET-1',
            'program_type' => ProgramType::CombinedPathology,
            'status' => 'retired',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.checklists.show', $version))
            ->assertOk();
    }

    /* 9. Empty versions cannot be published. */
    public function test_empty_version_cannot_be_published(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = ChecklistVersion::factory()->create([
            'version_code' => 'EMPTY',
            'program_type' => ProgramType::CombinedPathology,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.checklists.publish', $version))
            ->assertRedirect(route('admin.checklists.publish.confirm', $version));

        $this->assertDatabaseHas('checklist_versions', ['id' => $version->id, 'status' => 'draft']);
    }

    /* 10. Invalid requirements prevent publication. */
    public function test_invalid_requirements_prevent_publication(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = ChecklistVersion::factory()->create([
            'version_code' => 'BAD',
            'program_type' => ProgramType::CombinedPathology,
        ]);
        $section = ChecklistSection::factory()->create([
            'checklist_version_id' => $version->id,
            'title' => 'Section',
            'is_active' => true,
        ]);
        // Active requirement with an empty title is invalid.
        ChecklistRequirement::factory()->for($section, 'section')->create([
            'checklist_version_id' => $version->id,
            'code' => 'BAD-1',
            'requirement' => '', // invalid: empty title
            'response_type' => 'text',
            'is_active' => true,
            'severity' => 'minor',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.checklists.publish', $version))
            ->assertRedirect(route('admin.checklists.publish.confirm', $version));

        $this->assertDatabaseHas('checklist_versions', ['id' => $version->id, 'status' => 'draft']);
    }

    /* 11. Publishing records the publishing user and timestamp. */
    public function test_publishing_records_user_and_timestamp(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = $this->publishableVersion();

        $this->actingAs($admin)
            ->post(route('admin.checklists.publish', $version))
            ->assertRedirect(route('admin.checklists.show', $version));

        $version->refresh();
        $this->assertTrue($version->status->isPublished());
        $this->assertNotNull($version->published_at);
        $this->assertEquals($admin->id, $version->published_by);
    }

    /* 12. Publishing creates an audit-log entry. */
    public function test_publishing_creates_audit_entry(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = $this->publishableVersion();

        $this->actingAs($admin)->post(route('admin.checklists.publish', $version));

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => AuditAction::ChecklistVersionPublished->value,
            'auditable_type' => ChecklistVersion::class,
            'auditable_id' => $version->id,
        ]);
    }

    /* 13. Retiring records the retiring user and timestamp. */
    public function test_retiring_records_user_and_timestamp(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = ChecklistVersion::factory()->published()->create([
            'version_code' => 'RET-2',
            'program_type' => ProgramType::CombinedPathology,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.checklists.retire', $version), ['retirement_reason' => 'superseded'])
            ->assertRedirect(route('admin.checklists.show', $version));

        $version->refresh();
        $this->assertTrue($version->status->isRetired());
        $this->assertNotNull($version->retired_at);
        $this->assertEquals($admin->id, $version->retired_by);
    }

    /* 14. Retiring creates an audit-log entry. */
    public function test_retiring_creates_audit_entry(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = ChecklistVersion::factory()->published()->create([
            'version_code' => 'RET-3',
            'program_type' => ProgramType::CombinedPathology,
        ]);

        $this->actingAs($admin)->post(route('admin.checklists.retire', $version), []);

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'action' => AuditAction::ChecklistVersionRetired->value,
            'auditable_type' => ChecklistVersion::class,
            'auditable_id' => $version->id,
        ]);
    }

    /* 15. Cloning creates an independent draft. */
    public function test_cloning_creates_independent_draft(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $source = $this->publishableVersion();

        $this->actingAs($admin)
            ->post(route('admin.checklists.clone', $source))
            ->assertRedirect();

        $clone = ChecklistVersion::where('status', 'draft')
            ->where('id', '!=', $source->id)
            ->latest()
            ->firstOrFail();

        $this->assertEquals($source->program_type, $clone->program_type);
        $this->assertEquals(
            $source->sections()->count(),
            $clone->sections()->count(),
        );
        $this->assertTrue($clone->status->isEditable());
    }

    /* 16. Editing a cloned draft does not change the source version. */
    public function test_editing_clone_does_not_change_source(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $source = $this->publishableVersion();
        $sourceSectionCount = $source->sections()->count();

        $this->actingAs($admin)->post(route('admin.checklists.clone', $source));
        $clone = ChecklistVersion::where('status', 'draft')
            ->where('id', '!=', $source->id)
            ->latest()
            ->firstOrFail();

        // Modify the clone; source must be untouched.
        ChecklistSection::factory()->create([
            'checklist_version_id' => $clone->id,
            'title' => 'Added to clone only',
        ]);

        $this->assertEquals($sourceSectionCount, $source->sections()->count());
        $this->assertNotEquals($clone->sections()->count(), $source->sections()->count());
    }

    /* 17. Existing applications retain their original checklist version. */
    public function test_existing_applications_retain_original_version(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $applicant = $this->userWithRole(RoleName::TrainingOfficer, Institution::factory()->create());

        $version = ChecklistVersion::factory()->published()->create([
            'version_code' => 'APP-VER',
            'program_type' => ProgramType::CombinedPathology,
        ]);
        $program = Program::factory()->create(['program_type' => ProgramType::CombinedPathology]);
        $application = AccreditationApplication::factory()->create([
            'program_id' => $program->id,
            'checklist_version_id' => $version->id,
        ]);

        // Retire the version — the application must still reference it.
        $this->actingAs($admin)->post(route('admin.checklists.retire', $version), []);
        $application->refresh();

        $this->assertEquals($version->id, $application->checklist_version_id);
        $this->assertTrue($version->fresh()->status->isRetired());
    }

    /* 18. New applications load the current published checklist version. */
    public function test_new_applications_load_current_published_version(): void
    {
        $published = ChecklistVersion::factory()->published()->create([
            'version_code' => 'CUR-1',
            'program_type' => ProgramType::CombinedPathology,
        ]);
        // Ensure exactly one published version exists for the program type so
        // the wizard's resolution is deterministic.
        ChecklistVersion::query()->whereKeyNot($published->getKey())->delete();

        $institution = Institution::factory()->create();
        $program = Program::factory()->for($institution)->create([
            'program_type' => ProgramType::CombinedPathology,
            'status' => 'active',
        ]);
        $applicant = $this->userWithRole(RoleName::TrainingOfficer, $institution);

        $this->actingAs($applicant)
            ->post(route('applications.store'), [
                'program_id' => $program->id,
                'application_type' => 'new',
            ])
            ->assertRedirect();

        $application = AccreditationApplication::query()->latest()->firstOrFail();
        $this->assertEquals($published->id, $application->checklist_version_id);
    }

    /* 19. Institution users cannot access checklist routes by changing URL IDs. */
    public function test_institution_user_cannot_access_by_url_tampering(): void
    {
        $admin = $this->userWithRole(RoleName::InstitutionAdministrator, Institution::factory()->create());
        $version = $this->draftChecklist()['version'];

        $this->actingAs($admin)
            ->get(route('admin.checklists.show', $version))
            ->assertForbidden();
        $this->actingAs($admin)
            ->get(route('admin.checklists.edit', $version))
            ->assertForbidden();
    }

    /* 20. Publishing and cloning are transactional. */
    public function test_publishing_is_transactional(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = $this->publishableVersion();

        $before = DB::transactionLevel();
        $this->actingAs($admin)->post(route('admin.checklists.publish', $version));

        $this->assertEquals($before, DB::transactionLevel());
        $this->assertTrue($version->fresh()->status->isPublished());
    }

    /**
     * A draft version that satisfies every publication precondition.
     */
    private function publishableVersion(): ChecklistVersion
    {
        $version = ChecklistVersion::factory()->create([
            'version_code' => 'PUB-READY',
            'program_type' => ProgramType::CombinedPathology,
        ]);
        $section = ChecklistSection::factory()->create([
            'checklist_version_id' => $version->id,
            'title' => 'Ready Section',
            'is_active' => true,
        ]);
        ChecklistRequirement::factory()->for($section, 'section')->create([
            'checklist_version_id' => $version->id,
            'code' => 'READY-1',
            'requirement' => '[SYNTHETIC] Ready requirement.',
            'response_type' => 'text',
            'severity' => 'minor',
            'is_active' => true,
        ]);

        return $version;
    }
}
