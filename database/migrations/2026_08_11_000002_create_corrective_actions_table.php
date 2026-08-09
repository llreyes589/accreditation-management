<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A corrective action assigned against a finding. A finding may carry one
     * or more corrective actions. Responsible user/role and due date are
     * tracked; institution users submit responses and evidence for review.
     */
    public function up(): void
    {
        Schema::create('corrective_actions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('finding_id')->constrained('findings')->cascadeOnDelete();
            $table->string('action_reference')->unique();
            $table->string('title');
            $table->text('description')->nullable();

            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('responsible_role')->nullable();

            $table->timestamp('due_date')->nullable();
            $table->string('status')->default('open');

            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('accepted_at')->nullable();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('rejection_reason')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['finding_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_actions');
    }
};
