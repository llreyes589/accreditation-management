<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An inspection scheduled for an accreditation application that is complete
     * for inspection.
     *
     * All timestamps are stored in UTC; the application layer converts to the
     * configured display timezone (Asia/Manila) for presentation.
     */
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('application_id')->constrained('accreditation_applications')->cascadeOnDelete();
            $table->foreignId('checklist_version_id')->nullable()->constrained('checklist_versions')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->string('inspection_type')->default('onsite'); // onsite | virtual
            $table->timestamp('scheduled_start_at')->nullable();
            $table->timestamp('scheduled_end_at')->nullable();
            $table->string('timezone')->default('Asia/Manila');
            $table->text('location')->nullable();
            $table->string('meeting_url')->nullable();
            $table->text('instructions')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('scheduled_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();

            $table->timestamps();

            $table->index(['application_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
