<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a checklist requirement permits more than one supporting document.
     */
    public function up(): void
    {
        Schema::table('checklist_requirements', function (Blueprint $table) {
            $table->boolean('allows_multiple_documents')->default(false)->after('requires_document');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_requirements', function (Blueprint $table) {
            $table->dropColumn('allows_multiple_documents');
        });
    }
};
