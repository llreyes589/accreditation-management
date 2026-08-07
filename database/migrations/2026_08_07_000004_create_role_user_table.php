<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            // Null institution_id = the role is held globally (e.g. PSP
            // Administrator). A non-null value scopes the role to one institution.
            $table->foreignId('institution_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id', 'institution_id'], 'role_user_unique_assignment');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_user');
    }
};
