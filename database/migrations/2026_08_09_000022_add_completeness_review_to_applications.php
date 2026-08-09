<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Completeness-review state columns on the application.
     *
     * These are nullable so draft/submitted applications carry no review data.
     * They record the current review cycle only; the full history lives in the
     * append-only completeness_reviews table.
     */
    public function up(): void
    {
        Schema::table('accreditation_applications', function (Blueprint $table): void {
            $table->timestamp('under_review_at')->nullable()->after('submitted_at');
            $table->foreignId('under_review_by')->nullable()->after('under_review_at')->constrained('users')->nullOnDelete();

            $table->timestamp('returned_at')->nullable()->after('under_review_by');
            $table->foreignId('returned_by')->nullable()->after('returned_at')->constrained('users')->nullOnDelete();
            $table->text('returned_comment')->nullable()->after('returned_by');

            $table->timestamp('completed_for_inspection_at')->nullable()->after('returned_comment');
            $table->foreignId('completed_for_inspection_by')->nullable()->after('completed_for_inspection_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('accreditation_applications', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('completed_for_inspection_by');
            $table->dropColumn('completed_for_inspection_at');
            $table->dropColumn('returned_comment');
            $table->dropConstrainedForeignId('returned_by');
            $table->dropColumn('returned_at');
            $table->dropConstrainedForeignId('under_review_by');
            $table->dropColumn('under_review_at');
        });
    }
};
