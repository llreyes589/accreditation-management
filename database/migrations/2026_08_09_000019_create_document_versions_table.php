<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One immutable uploaded file version. The physical file is never edited;
     * replacements create a new row rather than overwriting this one.
     */
    public function up(): void
    {
        Schema::create('document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('documents')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('original_filename'); // display only; never used in the storage path
            $table->string('generated_filename'); // server-side UUID, unique on disk
            $table->string('storage_disk');
            $table->string('storage_path'); // relative to disk root; never exposed to the client
            $table->string('mime_type');
            $table->string('file_extension', 20);
            $table->unsignedBigInteger('file_size_bytes');
            $table->string('sha256_hash', 64);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamp('superseded_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'version_number']);
            $table->index('sha256_hash');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_versions');
    }
};
