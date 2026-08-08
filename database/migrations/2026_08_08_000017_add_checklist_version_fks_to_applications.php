<?php

use App\Models\AccreditationApplication;
use App\Models\ApplicationRequirement;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Capture the checklist version on every application and response row.
 *
 * Previously applications stored only a free-text `checklist_version` string
 * and responses linked straight to checklist_requirement_id. This migration
 * adds real foreign keys so the exact version used by an application is
 * recorded and historical responses keep pointing at their version even after
 * newer versions are published.
 *
 * Backfill: the version is resolved from the application's stored
 * version_code (defaulting to the initially published "2026.1"), and each
 * response inherits the version of its linked requirement.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_requirements', function (Blueprint $table) {
            $table->foreignId('checklist_version_id')->nullable()->after('checklist_requirement_id');
        });

        Schema::table('accreditation_applications', function (Blueprint $table) {
            $table->foreignId('checklist_version_id')->nullable()->after('checklist_version');
        });

        // Map every application to the version whose code matches its stored
        // string (falling back to the first published version).
        $defaultVersion = ChecklistVersion::query()->where('status', 'published')->first();

        AccreditationApplication::query()->each(function (AccreditationApplication $application) use ($defaultVersion): void {
            $version = null;

            if ($application->checklist_version !== null) {
                $version = ChecklistVersion::query()
                    ->where('version_code', $application->checklist_version)
                    ->first();
            }

            $version ??= $defaultVersion;

            $application->update(['checklist_version_id' => $version?->id]);

            // Responses inherit the version of the requirement they answer.
            $application->requirements()->each(function (ApplicationRequirement $row) use ($version): void {
                /** @var ChecklistRequirement|null $requirement */
                $requirement = ChecklistRequirement::query()->find($row->checklist_requirement_id);

                $row->update([
                    'checklist_version_id' => $requirement === null
                        ? $version->id
                        : $requirement->checklist_version_id,
                ]);
            });
        });

        Schema::table('application_requirements', function (Blueprint $table) {
            $table->foreign('checklist_version_id')
                ->references('id')->on('checklist_versions')
                ->restrictOnDelete();
        });
        Schema::table('accreditation_applications', function (Blueprint $table) {
            $table->foreign('checklist_version_id')
                ->references('id')->on('checklist_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('application_requirements', function (Blueprint $table) {
            $table->dropForeign(['checklist_version_id']);
            $table->dropColumn('checklist_version_id');
        });

        Schema::table('accreditation_applications', function (Blueprint $table) {
            $table->dropForeign(['checklist_version_id']);
            $table->dropColumn('checklist_version_id');
        });
    }
};
