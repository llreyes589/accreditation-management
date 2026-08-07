<?php

use App\Enums\ApplicantResponseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs a double-quoted column default introduced by renameColumn().
 *
 * On MySQL/MariaDB, renaming a column re-emits its existing DEFAULT and the
 * value gets quoted a second time, so `not_started` became `'not_started'`
 * (apostrophes included). Every row written since then stored that literal,
 * which is not a valid backing value for ApplicantResponseStatus.
 *
 * SQLite does not reproduce the fault, which is why the test suite stayed
 * green: this migration is the safety net for the MySQL schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Restate the default cleanly. change() rewrites the column definition
        // rather than re-emitting the corrupted one.
        Schema::table('application_requirements', function (Blueprint $table) {
            $table->string('applicant_status')
                ->default(ApplicantResponseStatus::NotStarted->value)
                ->change();
        });

        // Strip stray apostrophes from any value already written.
        foreach (ApplicantResponseStatus::cases() as $case) {
            DB::table('application_requirements')
                ->where('applicant_status', "'".$case->value."'")
                ->update(['applicant_status' => $case->value]);
        }

        // Anything still unparseable falls back to the documented default.
        DB::table('application_requirements')
            ->whereNotIn('applicant_status', ApplicantResponseStatus::values())
            ->update(['applicant_status' => ApplicantResponseStatus::NotStarted->value]);
    }

    public function down(): void
    {
        // The previous state was corrupt; there is nothing meaningful to
        // restore. Leaving the column clean is the only sane rollback.
    }
};
