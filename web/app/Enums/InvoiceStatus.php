<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Nenaplaceno = 'nenaplaceno';
    case Placeno = 'placeno';
    case Refundirano = 'refundirano';
    case DjelimicnoRefundirano = 'djelimicno_refundirano';
    case BezNaplate = 'bez_naplate';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
