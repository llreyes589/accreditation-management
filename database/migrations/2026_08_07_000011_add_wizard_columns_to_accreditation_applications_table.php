<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Extends the foundation applications table with the fields the accreditation
 * application wizard persists.
 *
 * Normalised columns are used for anything searched, filtered, reported on or
 * validated (type, status, step, percentage, dates, actors). JSON is reserved
 * for the five genuinely free-form narrative form sections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accreditation_applications', function (Blueprint $table) {
            $table->renameColumn('reference', 'application_reference');
        });

        Schema::table('accreditation_applications', function (Blueprint $table) {
            $table->string('application_type')->default('new')->after('program_id');
            // The checklist edition in force when the application was created.
            // Frozen for the life of the application.
            $table->string('checklist_version')->nullable()->after('application_type');
            $table->string('current_step')->default('program_selection')->after('status');
            $table->unsignedTinyInteger('completion_percentage')->default(0)->after('current_step');

            // Free-form wizard sections.
            $table->json('program_details')->nullable()->after('completion_percentage');
            $table->json('institution_information')->nullable()->after('program_details');
            $table->json('leadership_staffing')->nullable()->after('institution_information');
            $table->json('facilities_services')->nullable()->after('leadership_staffing');
            $table->json('curriculum_training')->nullable()->after('facilities_services');

            $table->timestamp('declaration_accepted_at')->nullable()->after('curriculum_training');
            $table->foreignId('declaration_accepted_by')->nullable()->after('declaration_accepted_at')
                ->constrained('users')->nullOnDelete();

            $table->timestamp('withdrawn_at')->nullable()->after('submitted_at');
            $table->foreignId('withdrawn_by')->nullable()->after('withdrawn_at')
                ->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->after('withdrawn_by')
                ->constrained('users')->nullOnDelete();

            // Supports the duplicate-active-application guard.
            $table->index(
                ['program_id', 'application_type', 'status'],
                'applications_program_type_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('accreditation_applications', function (Blueprint $table) {
            $table->dropIndex('applications_program_type_status_index');

            $table->dropForeign(['declaration_accepted_by']);
            $table->dropForeign(['withdrawn_by']);
            $table->dropForeign(['created_by']);

            $table->dropColumn([
                'application_type',
                'checklist_version',
                'current_step',
                'completion_percentage',
                'program_details',
                'institution_information',
                'leadership_staffing',
                'facilities_services',
                'curriculum_training',
                'declaration_accepted_at',
                'declaration_accepted_by',
                'withdrawn_at',
                'withdrawn_by',
                'created_by',
            ]);
        });

        Schema::table('accreditation_applications', function (Blueprint $table) {
            $table->renameColumn('application_reference', 'reference');
        });
    }
};
