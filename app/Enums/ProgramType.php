<?php

namespace App\Enums;

/**
 * Initial program types from PROJECT_CONTEXT.md.
 *
 * Stored as a string column so additional types can be introduced by data
 * without a schema change; this enum only provides the seeded starting set
 * and display labels.
 */
enum ProgramType: string
{
    case AnatomicPathology = 'anatomic_pathology';
    case ClinicalPathology = 'clinical_pathology';
    case CombinedPathology = 'combined_pathology';

    public function label(): string
    {
        return match ($this) {
            self::AnatomicPathology => 'Anatomic Pathology',
            self::ClinicalPathology => 'Clinical Pathology',
            self::CombinedPathology => 'Combined Anatomic and Clinical Pathology',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
