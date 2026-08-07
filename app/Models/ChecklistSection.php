<?php

namespace App\Models;

use Database\Factories\ChecklistSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['code', 'title', 'description', 'position', 'is_active'])]
class ChecklistSection extends Model
{
    /** @use HasFactory<ChecklistSectionFactory> */
    use HasFactory;

    /** @return HasMany<ChecklistRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(ChecklistRequirement::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'position' => 'integer',
        ];
    }
}
