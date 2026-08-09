<?php

namespace App\Models;

use Database\Factories\CorrectiveActionEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A link between a corrective-action submission and a privately stored document.
 *
 * The physical file stays inside the existing document subsystem (versioning,
 * authorization, secure download). This row only stores the association plus an
 * institution-provided note. Deleting the link never deletes the document.
 *
 * @property int $id
 * @property int $corrective_action_id
 * @property int $document_id
 * @property string|null $description
 * @property int|null $uploaded_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read CorrectiveAction $correctiveAction
 * @property-read Document $document
 * @property-read User|null $uploader
 */
#[Fillable([
    'corrective_action_id',
    'document_id',
    'description',
    'uploaded_by',
])]
class CorrectiveActionEvidence extends Model
{
    /** @use HasFactory<CorrectiveActionEvidenceFactory> */
    use HasFactory;

    /** @return BelongsTo<CorrectiveAction, $this> */
    public function correctiveAction(): BelongsTo
    {
        return $this->belongsTo(CorrectiveAction::class, 'corrective_action_id');
    }

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
}
