<?php

namespace App\Enums;

enum CityStatus: string
{
    case Aktivan = 'aktivan';
    case UPripremi = 'u_pripremi';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
