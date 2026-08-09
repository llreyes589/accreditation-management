<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only completeness-review history.
     *
     * Each reviewer action (begin review, return for revision, mark complete)
     * and each applicant resubmission appends a row. The deficiency snapshot is
     * captured as JSON so the completeness report reflects the state at the
     * time of the decision, even after the applicant makes changes.
     */
    public function up(): void
    {
        Schema::create('completeness_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('accreditation_applications')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision'); // under_review | returned_for_revision | complete_for_inspection | resubmitted
            $table->text('reviewer_comment')->nullable();
            $table->json('deficiencies')->nullable(); // captured snapshot for the report
            $table->timestamps();

            $table->index(['application_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('completeness_reviews');
    }
};
