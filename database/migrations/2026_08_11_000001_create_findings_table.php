<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A finding raised from a completed (or submitted) inspection criterion.
     *
     * The inspection checklist snapshot is immutable; the historical context
     * (requirement code/title/description, section name, severity at inspection
     * time, result, inspector notes, institution response) is copied here on
     * creation and must never change when the live checklist or inspection is
     * later edited.
     *
     * Timestamps are UTC. The application layer converts to the configured
     * display timezone (Asia/Manila) for presentation.
     */
    public function up(): void
    {
        Schema::create('findings', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('inspection_criterion_id')->nullable()->constrained('inspection_criteria')->nullOnDelete();
            $table->foreignId('application_id')->constrained('accreditation_applications')->cascadeOnDelete();

            $table->string('finding_reference')->unique();
            $table->string('category');          // classification snapshot (config-driven vocabulary)
            $table->string('severity');          // severity snapshot at inspection time
            $table->string('title');
            $table->text('description')->nullable();

            // Immutable historical context copied from the inspection criterion.
            $table->string('source_result')->nullable();
            $table->string('source_section_name')->nullable();
            $table->string('source_requirement_code')->nullable();
            $table->string('source_requirement_title')->nullable();
            $table->text('source_requirement_description')->nullable();
            $table->text('source_inspector_notes')->nullable();
            $table->text('source_institution_response')->nullable();

            $table->string('status')->default('draft');

            $table->timestamp('due_date')->nullable();
            $table->timestamp('institution_acknowledged_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('waived_at')->nullable();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('void_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['inspection_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('findings');
    }
};
