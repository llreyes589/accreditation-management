<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checklist_section_id')->constrained()->cascadeOnDelete();
            $table->string('code')->unique();
            $table->text('requirement');
            $table->text('guidance')->nullable();
            // Weight and threshold are configurable scoring inputs; no PSP
            // values are supplied by this migration.
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('pass_threshold')->nullable();
            $table->boolean('is_mandatory')->default(false);
            // Null = applies to every program type. Otherwise a JSON array of
            // program_type values this requirement applies to.
            $table->json('applicable_program_types')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['checklist_section_id', 'position']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_requirements');
    }
};
