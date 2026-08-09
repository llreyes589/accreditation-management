<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Append-only history of review actions on an inspection (returned for
     * revision, completed). Extends the inspection's audit trail with the
     * before/after status and reviewer commentary.
     */
    public function up(): void
    {
        Schema::create('inspection_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status_before')->nullable();
            $table->string('status_after')->nullable();
            $table->text('summary')->nullable();
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_reviews');
    }
};
