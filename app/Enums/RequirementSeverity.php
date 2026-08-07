<?php

namespace App\Enums;

/**
 * Weighting classification of a checklist requirement.
 *
 * The taxonomy is structural; which requirement carries which severity is
 * configurable data supplied by the checklist tables.
 */
enum RequirementSeverity: string
{
    case Major = 'major';
    case Minor = 'minor';
    case Informational = 'informational';

    public function label(): string
    {
        return match ($this) {
            self::Major => 'Major',
            self::Minor => 'Minor',
            self::Informational => 'Informational',
        };
    }
}
