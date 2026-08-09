<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Historical snapshot of each checklist requirement at the moment the
     * inspection was created.
     *
     * The copied section name, requirement title/description, severity and
     * ordering are stored here so the inspection record is immutable even when
     * the source checklist definition is later edited, published, retired or
     * deleted. source_requirement_id is retained for traceability only and is
     * never used as the source of displayed data.
     */
    public function up(): void
    {
        Schema::create('inspection_criteria', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inspection_id')->constrained('inspections')->cascadeOnDelete();
            $table->foreignId('source_requirement_id')->nullable()->constrained('checklist_requirements')->nullOnDelete();
            $table->string('section_code')->nullable();
            $table->string('section_name')->nullable();
            $table->string('requirement_code')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('severity')->nullable();
            $table->integer('display_order')->default(0);
            $table->string('response_type')->nullable();
            $table->string('result')->default('not_started'); // inspection result values
            $table->text('inspector_notes')->nullable();
            $table->text('institution_response')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['inspection_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspection_criteria');
    }
};
