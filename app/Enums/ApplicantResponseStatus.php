<?php

namespace App\Enums;

/**
 * The applicant's own assessment of a checklist requirement.
 *
 * This is deliberately separate from ReviewerResponseStatus: an applicant
 * marking a requirement Complete is a claim, not an acceptance.
 */
enum ApplicantResponseStatus: string
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Complete = 'complete';
    case NotApplicable = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::NotStarted => 'Not Started',
            self::InProgress => 'In Progress',
            self::Complete => 'Complete',
            self::NotApplicable => 'Not Applicable',
        };
    }

    /**
     * Statuses the applicant may actively select in the wizard.
     *
     * NotStarted is the system default and is not user-selectable.
     *
     * @return array<int, self>
     */
    public static function selectableCases(): array
    {
        return [self::Complete, self::NotApplicable, self::InProgress];
    }

    /**
     * @return array<int, string>
     */
    public static function selectableValues(): array
    {
        return array_map(fn (self $s): string => $s->value, self::selectableCases());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $s): string => $s->value, self::cases());
    }

    /**
     * Whether this status requires a written explanation from the applicant.
     */
    public function requiresExplanation(): bool
    {
        return $this === self::NotApplicable;
    }

    /**
     * Whether this status satisfies a mandatory requirement for submission.
     */
    public function satisfiesMandatory(): bool
    {
        return in_array($this, [self::Complete, self::NotApplicable], true);
    }
}
