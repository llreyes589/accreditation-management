<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Mutations to the sections and requirements belonging to a DRAFT version.
 *
 * Every change is audit logged and runs inside a transaction. These mutations
 * are only ever invoked for DRAFT versions — immutability of PUBLISHED and
 * RETIRED versions is enforced upstream by the policy and the controller.
 */
class ChecklistContentService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createSection(User $user, ChecklistVersion $version, array $data): ChecklistSection
    {
        return DB::transaction(function () use ($user, $version, $data): ChecklistSection {
            /** @var ChecklistSection $section */
            $section = $version->sections()->create([
                'code' => $data['code'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'position' => $data['position'] ?? ((int) ($version->sections()->max('position') ?? -1) + 1),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->audit->log(AuditAction::ChecklistSectionCreated, $user, $section, null, [
                'version_code' => $version->version_code,
                'code' => $section->code,
            ]);

            return $section;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateSection(User $user, ChecklistSection $section, array $data): ChecklistSection
    {
        return DB::transaction(function () use ($user, $section, $data): ChecklistSection {
            $before = $section->only(['code', 'title', 'description', 'position', 'is_active']);

            $section->update($data);

            $this->audit->log(AuditAction::ChecklistSectionUpdated, $user, $section, null, [
                'version_code' => $section->version->version_code,
                'before' => $before,
                'after' => $section->only(['code', 'title', 'description', 'position', 'is_active']),
            ]);

            return $section;
        });
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorderSections(User $user, ChecklistVersion $version, array $orderedIds): void
    {
        DB::transaction(function () use ($user, $version, $orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                $version->sections()->whereKey($id)->update(['position' => $position]);
            }

            $this->audit->log(AuditAction::ChecklistSectionReordered, $user, $version, null, [
                'version_code' => $version->version_code,
                'order' => $orderedIds,
            ]);
        });
    }

    public function deactivateSection(User $user, ChecklistSection $section, bool $active): ChecklistSection
    {
        return DB::transaction(function () use ($user, $section, $active): ChecklistSection {
            $section->update(['is_active' => $active]);

            $this->audit->log(
                $active ? AuditAction::ChecklistSectionUpdated : AuditAction::ChecklistSectionDeactivated,
                $user,
                $section,
                null,
                ['version_code' => $section->version->version_code, 'is_active' => $active],
            );

            return $section;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createRequirement(User $user, ChecklistVersion $version, array $data): ChecklistRequirement
    {
        return DB::transaction(function () use ($user, $version, $data): ChecklistRequirement {
            /** @var ChecklistRequirement $requirement */
            $requirement = $version->requirements()->create([
                'checklist_section_id' => $data['checklist_section_id'],
                'code' => $data['code'],
                'requirement' => $data['requirement'],
                'guidance' => $data['guidance'] ?? null,
                'response_type' => $data['response_type'],
                'applicable_program_types' => $data['applicable_program_types'] ?? null,
                'severity' => $data['severity'],
                'weight' => $data['weight'] ?? 1,
                'pass_threshold' => null,
                'is_mandatory' => $data['is_mandatory'] ?? false,
                'is_reviewer_only' => $data['is_reviewer_only'] ?? false,
                'requires_document' => $data['requires_document'] ?? false,
                'position' => $data['position'] ?? ((int) ($version->requirements()->max('position') ?? -1) + 1),
                'is_active' => $data['is_active'] ?? true,
            ]);

            $this->audit->log(AuditAction::ChecklistRequirementCreated, $user, $requirement, null, [
                'version_code' => $version->version_code,
                'code' => $requirement->code,
            ]);

            return $requirement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRequirement(User $user, ChecklistRequirement $requirement, array $data): ChecklistRequirement
    {
        return DB::transaction(function () use ($user, $requirement, $data): ChecklistRequirement {
            $before = $requirement->only([
                'code', 'requirement', 'guidance', 'checklist_section_id', 'response_type',
                'applicable_program_types', 'severity', 'is_mandatory', 'is_reviewer_only',
                'requires_document', 'position', 'is_active',
            ]);

            $requirement->update($data);

            $this->audit->log(AuditAction::ChecklistRequirementUpdated, $user, $requirement, null, [
                'version_code' => $requirement->version->version_code,
                'before' => $before,
                'after' => $requirement->only([
                    'code', 'requirement', 'guidance', 'checklist_section_id', 'response_type',
                    'applicable_program_types', 'severity', 'is_mandatory', 'is_reviewer_only',
                    'requires_document', 'position', 'is_active',
                ]),
            ]);

            return $requirement;
        });
    }

    /**
     * @param  array<int, int>  $orderedIds
     */
    public function reorderRequirements(User $user, ChecklistSection $section, array $orderedIds): void
    {
        DB::transaction(function () use ($user, $section, $orderedIds): void {
            foreach ($orderedIds as $position => $id) {
                $section->requirements()->whereKey($id)->update(['position' => $position]);
            }

            $this->audit->log(AuditAction::ChecklistRequirementReordered, $user, $section, null, [
                'version_code' => $section->version->version_code,
                'section_code' => $section->code,
                'order' => $orderedIds,
            ]);
        });
    }

    public function deactivateRequirement(User $user, ChecklistRequirement $requirement, bool $active): ChecklistRequirement
    {
        return DB::transaction(function () use ($user, $requirement, $active): ChecklistRequirement {
            $requirement->update(['is_active' => $active]);

            $this->audit->log(
                $active ? AuditAction::ChecklistRequirementUpdated : AuditAction::ChecklistRequirementDeactivated,
                $user,
                $requirement,
                null,
                ['version_code' => $requirement->version->version_code, 'is_active' => $active],
            );

            return $requirement;
        });
    }
}
