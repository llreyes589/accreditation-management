<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a corrective-action submission to a privately stored document.
     *
     * Evidence reuses the existing private document system (storage, versioning,
     * authorization, and secure download) — this table only records the link
     * plus the institution's contextual note. File contents are never duplicated
     * and never stored in the audit log.
     */
    public function up(): void
    {
        Schema::create('corrective_action_evidence', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('corrective_action_id')->constrained('corrective_actions')->cascadeOnDelete();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['corrective_action_id', 'document_id'], 'cae_action_document_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_action_evidence');
    }
};
