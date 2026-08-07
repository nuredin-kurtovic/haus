<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Uplatnica = 'uplatnica';
    case Kartica = 'kartica';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
