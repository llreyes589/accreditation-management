<?php

namespace App\Models;

use Database\Factories\InspectionEvidenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An evidence reference attached to an inspection criterion.
 *
 * Stores only a reference to an existing {@see Document} (never the file
 * contents) so evidence reuses the secure document authorization system.
 *
 * @property int $id
 * @property int $inspection_id
 * @property int|null $inspection_criterion_id
 * @property int|null $document_id
 * @property string|null $note
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Inspection $inspection
 * @property-read InspectionCriterion|null $criterion
 * @property-read Document|null $document
 * @property-read User|null $creator
 */
#[Fillable([
    'inspection_id',
    'inspection_criterion_id',
    'document_id',
    'note',
    'created_by',
])]
class InspectionEvidence extends Model
{
    /** @use HasFactory<InspectionEvidenceFactory> */
    use HasFactory;

    /** @return BelongsTo<Inspection, $this> */
    public function inspection(): BelongsTo
    {
        return $this->belongsTo(Inspection::class, 'inspection_id');
    }

    /** @return BelongsTo<InspectionCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(InspectionCriterion::class, 'inspection_criterion_id');
    }

    /** @return BelongsTo<Document, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class, 'document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
