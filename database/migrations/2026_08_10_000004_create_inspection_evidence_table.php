<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidence references attached to an inspection criterion.
     *
     * Reuses the existing secure document model; only a reference is stored
     * here, never the file contents, so the audit log and report stay light.
     */
    public function up(): void
    {
        Schema::create('inspection_evidence', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('inspection_criterion_id')->nullable()->constrained('inspection_criteria')->nullOnDelete();
            $table->foreignId('document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['inspection_id', 'inspection_criterion_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_evidence');
    }
};
