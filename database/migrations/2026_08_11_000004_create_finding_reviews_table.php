<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only history of finding lifecycle actions (created, acknowledged,
     * returned, accepted, rejected, reopened, closed, waived). Mirrors the
     * inspection review history pattern and supports a full audit trail of who
     * did what and when — never updated or deleted.
     */
    public function up(): void
    {
        Schema::create('finding_reviews', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('finding_id')->constrained('findings')->cascadeOnDelete();
            $table->foreignId('corrective_action_id')->nullable()->constrained('corrective_actions')->nullOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('action');
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->text('comment')->nullable();

            $table->timestamps();

            $table->index(['finding_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_reviews');
    }
};
