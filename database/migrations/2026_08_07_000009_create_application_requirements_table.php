<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accreditation_application_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->foreignId('checklist_requirement_id')
                ->constrained()
                ->restrictOnDelete();
            $table->string('status')->default('not_started');
            // Free-text narrative only. Supporting documents are a later
            // milestone and no patient-identifying content is stored here.
            $table->text('response')->nullable();
            $table->text('reviewer_comment')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['accreditation_application_id', 'checklist_requirement_id'],
                'application_requirement_unique'
            );
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_requirements');
    }
};
