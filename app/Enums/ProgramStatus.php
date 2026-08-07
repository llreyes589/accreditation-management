<?php

namespace App\Enums;

/**
 * Lifecycle status of a training program.
 *
 * Note: `Accredited` here records that an accreditation decision is currently
 * in force. The validity period itself is stored per program
 * (`accredited_until`) and is never derived from a hard-coded duration.
 */
enum ProgramStatus: string
{
    case Prospective = 'prospective';
    case Active = 'active';
    case Accredited = 'accredited';
    case Lapsed = 'lapsed';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Prospective => 'Prospective',
            self::Active => 'Active',
            self::Accredited => 'Accredited',
            self::Lapsed => 'Lapsed',
            self::Withdrawn => 'Withdrawn',
        };
    }

    /**
     * Whether a program in this state may be used for a new application.
     */
    public function isSelectableForApplication(): bool
    {
        return in_array($this, [self::Active, self::Accredited], true);
    }
}
