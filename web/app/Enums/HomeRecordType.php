<?php

namespace App\Enums;

enum HomeRecordType: string
{
    case Intervencija = 'intervencija';
    case Pregled = 'pregled';
    case Napomena = 'napomena';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
