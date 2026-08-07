<?php

use App\Enums\ApplicantResponseStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Separates applicant-supplied data from PSP/CART reviewer decisions.
 *
 * The foundation table carried a single ambiguous `status`/`response` pair.
 * The applicant's claim and the reviewer's verdict are distinct facts and must
 * never be conflated, so each now has its own columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_requirements', function (Blueprint $table) {
            $table->renameColumn('status', 'applicant_status');
            $table->renameColumn('response', 'applicant_response');
        });

        // MySQL/MariaDB re-quote the inherited default during a rename, which
        // yields the literal "'not_started'". Restate it explicitly so a fresh
        // install never carries the corrupted value.
        Schema::table('application_requirements', function (Blueprint $table) {
            $table->string('applicant_status')
                ->default(ApplicantResponseStatus::NotStarted->value)
                ->change();
        });

        Schema::table('application_requirements', function (Blueprint $table) {
            // Mandatory when the applicant marks a requirement Not Applicable.
            $table->text('applicant_explanation')->nullable()->after('applicant_response');
            $table->timestamp('responded_at')->nullable()->after('applicant_explanation');
            $table->foreignId('responded_by')->nullable()->after('responded_at')
                ->constrained('users')->nullOnDelete();

            // Reviewer-side verdict. Never written by the applicant wizard.
            $table->string('reviewer_status')->default('pending')->after('responded_by');

            $table->index('applicant_status');
            $table->index('reviewer_status');
        });
    }

    public function down(): void
    {
        Schema::table('application_requirements', function (Blueprint $table) {
            $table->dropIndex(['applicant_status']);
            $table->dropIndex(['reviewer_status']);
            $table->dropForeign(['responded_by']);
            $table->dropColumn([
                'applicant_explanation',
                'responded_at',
                'responded_by',
                'reviewer_status',
            ]);
        });

        Schema::table('application_requirements', function (Blueprint $table) {
            $table->renameColumn('applicant_status', 'status');
            $table->renameColumn('applicant_response', 'response');
        });
    }
};
