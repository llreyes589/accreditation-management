<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Immutable history of institution corrective-action submissions.
     *
     * Each submission the institution makes (proposed action, implementation
     * notes, and referenced evidence) is appended as a new version. Previously
     * submitted versions are never overwritten after review — the reviewer
     * always sees exactly what was submitted at the time.
     */
    public function up(): void
    {
        Schema::create('corrective_action_versions', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('corrective_action_id')->constrained('corrective_actions')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->text('proposed_action');
            $table->text('implementation_notes')->nullable();
            $table->json('evidence')->nullable(); // [{document_id, note}] references, never the file

            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();

            $table->timestamps();

            $table->index(['corrective_action_id', 'version_number'], 'ca_versions_ca_id_version_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('corrective_action_versions');
    }
};
