<?php

namespace App\Models;

use Database\Factories\DocumentVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One immutable uploaded file belonging to a {@see Document}.
 *
 * The physical file is never edited in place: a replacement creates a new
 * version rather than overwriting this row, so historical versions remain
 * intact for audit and review-history purposes.
 *
 * @property int $id
 * @property int $document_id
 * @property int $version_number
 * @property string $original_filename
 * @property string $generated_filename
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $mime_type
 * @property string $file_extension
 * @property int $file_size_bytes
 * @property string $sha256_hash
 * @property int|null $uploaded_by
 * @property Carbon $uploaded_at
 * @property Carbon|null $superseded_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 * @property-read User|null $uploader
 */
#[Fillable([
    'document_id',
    'version_number',
    'original_filename',
    'generated_filename',
    'storage_disk',
    'storage_path',
    'mime_type',
    'file_extension',
    'file_size_bytes',
    'sha256_hash',
    'uploaded_by',
    'uploaded_at',
    'superseded_at',
])]
class DocumentVersion extends Model
{
    /** @use HasFactory<DocumentVersionFactory> */
    use HasFactory;

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return HasMany<DocumentReview, $this> */
    public function reviews(): HasMany
    {
        return $this->hasMany(DocumentReview::class, 'document_version_id');
    }

    public function isSuperseded(): bool
    {
        return $this->superseded_at !== null;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'version_number' => 'integer',
            'file_size_bytes' => 'integer',
            'uploaded_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }
}
