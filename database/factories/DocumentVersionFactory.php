<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DocumentVersion>
 */
class DocumentVersionFactory extends Factory
{
    protected $model = DocumentVersion::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'version_number' => 1,
            'original_filename' => fake()->word().'.pdf',
            'generated_filename' => Str::uuid()->toString().'.pdf',
            'storage_disk' => 'accreditation',
            'storage_path' => 'accreditation/1/documents/1/'.Str::uuid()->toString().'.pdf',
            'mime_type' => 'application/pdf',
            'file_extension' => 'pdf',
            'file_size_bytes' => fake()->numberBetween(1024, 5 * 1024 * 1024),
            'sha256_hash' => hash('sha256', Str::random(64)),
            'uploaded_by' => User::factory(),
            'uploaded_at' => now(),
        ];
    }
}
