<?php

namespace App\Models;

use App\Enums\DocumentDecision;
use Database\Factories\DocumentReviewFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One reviewer decision recorded against a document version.
 *
 * @property int $id
 * @property int $document_id
 * @property int $document_version_id
 * @property int|null $reviewer_id
 * @property DocumentDecision $decision
 * @property string|null $comment
 * @property Carbon $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 * @property-read DocumentVersion $version
 * @property-read User|null $reviewer
 */
#[Fillable([
    'document_id',
    'document_version_id',
    'reviewer_id',
    'decision',
    'comment',
    'reviewed_at',
])]
class DocumentReview extends Model
{
    /** @use HasFactory<DocumentReviewFactory> */
    use HasFactory;

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** @return BelongsTo<DocumentVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'document_version_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'decision' => DocumentDecision::class,
            'reviewed_at' => 'datetime',
        ];
    }
}
