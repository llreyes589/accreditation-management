<?php

namespace App\Enums;

/**
 * How an applicant answers a checklist requirement.
 *
 * Controlled values only — no response types are introduced that the existing
 * wizard cannot consume. The wizard currently renders YES_NO, TEXT, NUMBER,
 * DATE and SELECT; MULTI_SELECT and ACKNOWLEDGEMENT are included for
 * completeness of the taxonomy but render as TEXT-equivalent prompts until a
 * later milestone adds dedicated UI.
 */
enum RequirementResponseType: string
{
    case YesNo = 'yes_no';
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case MultiSelect = 'multi_select';
    case Acknowledgement = 'acknowledgement';

    public function label(): string
    {
        return match ($this) {
            self::YesNo => 'Yes / No',
            self::Text => 'Free text',
            self::Number => 'Number',
            self::Date => 'Date',
            self::Select => 'Single choice',
            self::MultiSelect => 'Multiple choice',
            self::Acknowledgement => 'Acknowledgement',
        };
    }

    /**
     * Response types the wizard currently renders with dedicated controls.
     */
    public function isSupportedByWizard(): bool
    {
        return in_array($this, [
            self::YesNo,
            self::Text,
            self::Number,
            self::Date,
            self::Select,
        ], true);
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
