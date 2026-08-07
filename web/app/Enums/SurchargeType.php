<?php

namespace App\Enums;

enum SurchargeType: string
{
    case Percent = 'percent';
    case PerKm = 'per_km';
    case Flat = 'flat';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
