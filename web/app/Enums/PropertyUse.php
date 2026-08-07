<?php

namespace App\Enums;

enum PropertyUse: string
{
    case Zivim = 'zivim';
    case IzdajeSe = 'izdaje_se';
    case Prazan = 'prazan';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
