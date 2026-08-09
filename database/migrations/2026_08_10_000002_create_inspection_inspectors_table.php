<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Inspector assignments for an inspection.
     *
     * History is preserved: a removed inspector's row is retained (status set to
     * removed / declined) rather than deleted, so the audit trail survives the
     * inspection lifecycle.
     */
    public function up(): void
    {
        Schema::create('inspection_inspectors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role')->default('inspector'); // lead_inspector | inspector | observer
            $table->string('status')->default('invited'); // invited | accepted | declined | removed
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->text('decline_reason')->nullable();
            $table->timestamps();

            $table->unique(['inspection_id', 'user_id']);
            $table->index(['inspection_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_inspectors');
    }
};
