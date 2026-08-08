<?php

namespace App\Enums;

/**
 * Lifecycle status of a configurable checklist version.
 *
 * A version starts as DRAFT, may become PUBLISHED (immutable), and may later
 * be RETIRED. The vocabulary is intentionally small and matches the spec; the
 * behaviours (immutability, single-published-per-scope, retire-only) live in
 * the ChecklistVersion model and ChecklistVersionService.
 */
enum ChecklistVersionStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Published => 'Published',
            self::Retired => 'Retired',
        };
    }

    /**
     * Drafts can still be mutated; published and retired versions are frozen.
     */
    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isPublished(): bool
    {
        return $this === self::Published;
    }

    public function isRetired(): bool
    {
        return $this === self::Retired;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
