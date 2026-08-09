<?php

namespace App\Enums;

/**
 * Possible result values recorded against an inspection criterion.
 *
 * Controlled vocabulary so the inspection report stays machine-readable.
 */
enum InspectionResult: string
{
    case NotStarted = 'not_started';
    case Compliant = 'compliant';
    case PartiallyCompliant = 'partially_compliant';
    case Noncompliant = 'noncompliant';
    case NotApplicable = 'not_applicable';
    case NotObserved = 'not_observed';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::Compliant => 'Compliant',
            self::PartiallyCompliant => 'Partially Compliant',
            self::Noncompliant => 'Non-Compliant',
            self::NotApplicable => 'Not Applicable',
            self::NotObserved => 'Not Observed',
        };
    }

    /**
     * Results that count as an observed compliance outcome (i.e. not "no data").
     */
    public function isObserved(): bool
    {
        return $this !== self::NotStarted;
    }
}
