<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('programs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institution_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            // Program type is stored as a string so new types can be added as
            // configuration data without a schema change (docs/domain-model.md §4).
            $table->string('program_type');
            $table->string('status')->default('prospective');
            // Accreditation validity is recorded as an explicit date supplied by
            // an authorized decision; no duration is derived in code.
            $table->date('accredited_from')->nullable();
            $table->date('accredited_until')->nullable();
            $table->timestamps();

            $table->unique(['institution_id', 'program_type']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('programs');
    }
};
