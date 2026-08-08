<?php

namespace App\Services\Accreditation;

use App\Enums\ApplicationType;
use App\Models\AccreditationApplication;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistVersion;
use App\Models\Program;
use App\Services\ChecklistVersionService;
use Illuminate\Database\Eloquent\Collection;

/**
 * Resolves which checklist requirements apply to an application and
 * materialises the applicant's response rows.
 *
 * Requirements are always drawn from the published checklist version for the
 * program's type. The version is frozen onto the application at creation, so
 * resuming a draft keeps using the edition it started against — newer
 * published versions never alter an in-flight application.
 *
 * Requirements themselves are never created or edited here: the wizard is a
 * strict consumer of the configurable checklist.
 */
class ChecklistResponseSynchronizer
{
    public function __construct(
        private readonly ChecklistVersionService $versions,
    ) {}

    /**
     * The checklist version an application should use.
     *
     * If the application already captured a version (draft or submitted), that
     * frozen version wins. Otherwise the currently published version for the
     * program type is used — or null when nothing is published yet.
     */
    public function versionFor(AccreditationApplication $application): ?ChecklistVersion
    {
        if ($application->checklistVersion !== null) {
            return $application->checklistVersion;
        }

        $program = $application->program;

        if ($program === null) {
            return null;
        }

        return $this->versions->publishedVersionForProgramType($program->program_type);
    }

    /**
     * Active, applicant-answerable requirements for a program's type, scoped to
     * the given version.
     *
     * @return Collection<int, ChecklistRequirement>
     */
    public function applicableRequirements(Program $program, ChecklistVersion $version): Collection
    {
        return ChecklistRequirement::query()
            ->applicantAnswerable()
            ->where('checklist_version_id', $version->id)
            ->with('section')
            ->get()
            ->filter(fn (ChecklistRequirement $r): bool => $r->appliesTo($program->program_type))
            ->sort(function (ChecklistRequirement $a, ChecklistRequirement $b): int {
                $sectionA = $a->section === null ? 0 : $a->section->position;
                $sectionB = $b->section === null ? 0 : $b->section->position;

                return [$sectionA, $a->position] <=> [$sectionB, $b->position];
            })
            ->values();
    }

    /**
     * Ensure a response row exists for every applicable requirement.
     *
     * Existing rows are left untouched so applicant answers survive. Rows are
     * never deleted here: an application preserves the checklist edition it
     * was created against even if the configuration later changes.
     *
     * @return int Number of rows created.
     */
    public function sync(AccreditationApplication $application): int
    {
        $version = $this->versionFor($application);
        $program = $application->program;

        if ($version === null || $program === null) {
            return 0;
        }

        $applicable = $this->applicableRequirements($program, $version);

        $existing = $application->requirements()
            ->where('checklist_version_id', $version->id)
            ->pluck('checklist_requirement_id')
            ->all();

        $created = 0;

        foreach ($applicable as $requirement) {
            if (in_array($requirement->id, $existing, true)) {
                continue;
            }

            $application->requirements()->create([
                'checklist_requirement_id' => $requirement->id,
                'checklist_version_id' => $version->id,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Whether another active application already exists for this program and
     * application type, excluding the one being resumed.
     */
    public function hasDuplicateActiveApplication(
        Program $program,
        ApplicationType $type,
        ?int $ignoreApplicationId = null,
    ): bool {
        return AccreditationApplication::query()
            ->where('program_id', $program->id)
            ->where('application_type', $type)
            ->active()
            ->when($ignoreApplicationId !== null, fn ($q) => $q->whereKeyNot($ignoreApplicationId))
            ->exists();
    }
}
