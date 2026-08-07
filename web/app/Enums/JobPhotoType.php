<?php

namespace App\Enums;

enum JobPhotoType: string
{
    case Prije = 'prije';
    case Poslije = 'poslije';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
