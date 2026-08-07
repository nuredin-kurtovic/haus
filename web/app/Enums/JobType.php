<?php

namespace App\Enums;

enum JobType: string
{
    case Redovno = 'redovno';
    case Garancija = 'garancija';
    case Pregled = 'pregled';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
