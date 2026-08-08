<?php

namespace App\Enums;

/**
 * Whether a document satisfies a specific checklist requirement or is attached
 * at the application level.
 */
enum DocumentType: string
{
    case RequirementDocument = 'requirement_document';
    case ApplicationDocument = 'application_document';

    public function label(): string
    {
        return match ($this) {
            self::RequirementDocument => 'Requirement Document',
            self::ApplicationDocument => 'Application Document',
        };
    }
}
