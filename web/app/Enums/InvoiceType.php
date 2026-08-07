<?php

namespace App\Enums;

enum InvoiceType: string
{
    case Pretplata = 'pretplata';
    case Rad = 'rad';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
