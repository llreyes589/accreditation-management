<?php

use App\Enums\ChecklistVersionStatus;
use App\Enums\ProgramType;
use App\Enums\RequirementResponseType;
use App\Models\ChecklistRequirement;
use App\Models\ChecklistSection;
use App\Models\ChecklistVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Connect checklist sections and requirements to a version.
 *
 * Before this migration the checklist was a single global set with a globally
 * unique `code`. Versioning requires:
 *  - `checklist_version_id` on both tables (FK, cascade),
 *  - codes unique only *within* a version, not globally,
 *  - `response_type` and `requires_document` on requirements.
 *
 * Existing rows are re-parented to a freshly created initial "2026.1" published
 * version so that historical application_requirements still resolve through a
 * real version and the NOT NULL constraint can be applied.
 *
 * No application row is modified here: application_requirements already point
 * at checklist_requirement_id, and that link is preserved by the cascade.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_sections', function (Blueprint $table) {
            $table->foreignId('checklist_version_id')->nullable()->after('id');
            // Drop the legacy global uniqueness (code was globally unique
            // before versioning). Use the exact index name so the drop is not
            // dependent on Laravel's name resolution.
            $table->dropUnique('checklist_sections_code_unique');
        });

        Schema::table('checklist_requirements', function (Blueprint $table) {
            // Drop the legacy global uniqueness.
            $table->dropUnique('checklist_requirements_code_unique');
            $table->foreignId('checklist_version_id')->nullable()->after('id');
            $table->string('response_type')->default(RequirementResponseType::Text->value)->after('guidance');
            $table->boolean('requires_document')->default(false)->after('response_type');
        });

        // Establish composite uniqueness up front (SQLite-safe: no empty index
        // names), applied after rows are versioned.
        Schema::table('checklist_sections', function (Blueprint $table) {
            $table->unique(['checklist_version_id', 'code'], 'chk_sections_version_code');
        });
        Schema::table('checklist_requirements', function (Blueprint $table) {
            $table->unique(['checklist_version_id', 'code'], 'chk_requirements_version_code');
        });

        // Create the initial version and re-parent existing content.
        $version = ChecklistVersion::create([
            'version_code' => '2026.1',
            'name' => 'Initial Accreditation Checklist',
            'description' => 'Baseline checklist carried over from the pre-versioning configuration.',
            'program_type' => ProgramType::CombinedPathology->value,
            'status' => ChecklistVersionStatus::Published->value,
            'published_at' => now(),
            // created_by is nullable; no specific creator seeded historically.
        ]);

        ChecklistSection::query()->update(['checklist_version_id' => $version->id]);
        ChecklistRequirement::query()->update(['checklist_version_id' => $version->id]);

        // Now enforce non-nullability.
        Schema::table('checklist_sections', function (Blueprint $table) {
            $table->foreign('checklist_version_id')
                ->references('id')->on('checklist_versions')
                ->cascadeOnDelete();
        });
        Schema::table('checklist_requirements', function (Blueprint $table) {
            $table->foreign('checklist_version_id')
                ->references('id')->on('checklist_versions')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('checklist_requirements', function (Blueprint $table) {
            $table->dropForeign(['checklist_version_id']);
            $table->dropUnique('chk_requirements_version_code');
            $table->dropColumn(['checklist_version_id', 'response_type', 'requires_document']);
            $table->unique('code');
        });

        Schema::table('checklist_sections', function (Blueprint $table) {
            $table->dropForeign(['checklist_version_id']);
            $table->dropUnique('chk_sections_version_code');
            $table->dropColumn('checklist_version_id');
        });

        ChecklistVersion::query()->delete();
    }
};
