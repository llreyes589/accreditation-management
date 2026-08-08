<?php

use App\Enums\ChecklistVersionStatus;
use App\Enums\ProgramType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * New table: versioned, immutable-once-published checklist editions.
 *
 * A published version is the basis an application is created against and must
 * never be edited (see ChecklistVersionPolicy + ChecklistVersionService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checklist_versions', function (Blueprint $table) {
            $table->id();
            $table->string('version_code')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('program_type')->default(ProgramType::CombinedPathology->value);
            $table->string('status')->default(ChecklistVersionStatus::Draft->value);

            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('published_reason')->nullable();

            $table->timestamp('retired_at')->nullable();
            $table->foreignId('retired_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('retired_reason')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('program_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_versions');
    }
};
