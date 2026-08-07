<?php

namespace App\Enums;

enum ApplicationType: string
{
    case New = 'new';
    case Renewal = 'renewal';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New Accreditation',
            self::Renewal => 'Renewal',
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
