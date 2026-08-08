<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One review action / review record per reviewer decision.
     */
    public function up(): void
    {
        Schema::create('document_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->foreignId('document_version_id')->constrained('document_versions')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision'); // DocumentDecision value
            $table->text('comment')->nullable();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->index(['document_id', 'reviewed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_reviews');
    }
};
