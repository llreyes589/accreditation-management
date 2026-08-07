<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the severity taxonomy and the reviewer-only marker to the configurable
 * checklist. Both are configuration data supplied by seeds, not code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('checklist_requirements', function (Blueprint $table) {
            $table->string('severity')->default('minor')->after('guidance');
            // Requirements answered by PSP/CART during review. The applicant
            // wizard never renders or accepts input for these.
            $table->boolean('is_reviewer_only')->default(false)->after('is_mandatory');

            $table->index('severity');
            $table->index('is_reviewer_only');
        });
    }

    public function down(): void
    {
        Schema::table('checklist_requirements', function (Blueprint $table) {
            $table->dropIndex(['severity']);
            $table->dropIndex(['is_reviewer_only']);
            $table->dropColumn(['severity', 'is_reviewer_only']);
        });
    }
};
