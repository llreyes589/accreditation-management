<?php

namespace App\Enums;

/**
 * Status of an institution's response to a single checklist requirement.
 *
 * The pass/fail thresholds and scoring rules live in configurable checklist
 * data, not in this vocabulary.
 */
enum RequirementResponseStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Submitted = 'submitted';
    case Compliant = 'compliant';
    case NonCompliant = 'non_compliant';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::Submitted => 'Submitted',
            self::Compliant => 'Compliant',
            self::NonCompliant => 'Non-compliant',
            self::NotApplicable => 'Not Applicable',
        };
    }
}
