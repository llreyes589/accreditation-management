<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One logical document submission (may span several immutable versions).
     */
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained('accreditation_applications')->cascadeOnDelete();
            $table->foreignId('application_requirement_id')->nullable()->constrained('application_requirements')->nullOnDelete();
            $table->string('document_type'); // DocumentType value
            $table->string('status'); // DocumentStatus value
            $table->foreignId('current_version_id')->nullable(); // intentional: document_versions created in a later migration
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('last_reviewed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['application_id', 'status']);
            $table->index(['application_requirement_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
