<?php

namespace Tests\Unit\Services;

use App\Enums\ChecklistVersionStatus;
use App\Enums\ProgramType;
use App\Enums\RoleName;
use App\Exceptions\ChecklistVersionException;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use App\Services\ChecklistVersionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesFoundationData;
use Tests\TestCase;

/**
 * Unit coverage for the checklist version lifecycle service.
 */
class ChecklistVersionServiceTest extends TestCase
{
    use CreatesFoundationData;
    use RefreshDatabase;

    private ChecklistVersionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = $this->app->make(ChecklistVersionService::class);
    }

    public function test_publish_makes_version_immutable_and_records_actor(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = $this->publishable();

        $this->service->publish($admin, $version);

        $version->refresh();
        $this->assertTrue($version->status->isPublished());
        $this->assertEquals($admin->id, $version->published_by);
        $this->assertNotNull($version->published_at);
    }

    public function test_publishing_empty_version_is_rejected(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = ChecklistVersion::factory()->create([
            'version_code' => 'EMPTY-SVC',
            'program_type' => ProgramType::CombinedPathology,
        ]);

        $this->expectException(ChecklistVersionException::class);
        $this->service->publish($admin, $version);
    }

    public function test_only_one_published_version_per_program_type(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $existing = ChecklistVersion::factory()->published()->create([
            'version_code' => 'ALREADY',
            'program_type' => ProgramType::CombinedPathology,
        ]);
        $version = $this->publishable();

        $this->service->publish($admin, $version);

        // Publishing a new version retires the previously published one so
        // that exactly one published version exists per program type.
        $this->assertTrue($version->fresh()->status->isPublished());
        $this->assertTrue($existing->fresh()->status->isRetired());
    }

    public function test_retire_records_actor_and_keeps_requirements(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = $this->publishable();
        $this->service->publish($admin, $version);
        $requirementCount = $version->requirements()->count();

        $this->service->retire($admin, $version, 'superseded');

        $version->refresh();
        $this->assertTrue($version->status->isRetired());
        $this->assertEquals($admin->id, $version->retired_by);
        $this->assertEquals($requirementCount, $version->requirements()->count());
    }

    public function test_clone_copies_sections_and_requirements_as_draft(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $source = $this->publishable();
        $this->service->publish($admin, $source);

        $clone = $this->service->clone($admin, $source);

        $this->assertTrue($clone->status->isEditable());
        $this->assertEquals(ChecklistVersionStatus::Draft, $clone->status);
        $this->assertEquals($source->sections()->count(), $clone->sections()->count());
        $this->assertEquals($source->requirements()->count(), $clone->requirements()->count());
        $this->assertNotEquals($source->id, $clone->id);
    }

    public function test_published_version_cannot_be_deleted(): void
    {
        $admin = $this->userWithRole(RoleName::PspAdministrator);
        $version = ChecklistVersion::factory()->published()->create([
            'version_code' => 'NO-DELETE',
            'program_type' => ProgramType::CombinedPathology,
        ]);

        $this->expectException(ChecklistVersionException::class);
        $this->service->deleteDraft($admin, $version);
    }

    private function publishable(): ChecklistVersion
    {
        $version = ChecklistVersion::factory()->create([
            'version_code' => 'SVC-'.fake()->unique()->numerify('####'),
            'program_type' => ProgramType::CombinedPathology,
        ]);
        $section = ChecklistSection::factory()->create([
            'checklist_version_id' => $version->id,
            'title' => 'Section',
            'is_active' => true,
        ]);
        ChecklistRequirement::factory()->for($section, 'section')->create([
            'checklist_version_id' => $version->id,
            'code' => 'R-'.fake()->unique()->numerify('####'),
            'requirement' => '[SYNTHETIC] requirement',
            'response_type' => 'text',
            'severity' => 'minor',
            'is_active' => true,
        ]);

        return $version;
    }
}
