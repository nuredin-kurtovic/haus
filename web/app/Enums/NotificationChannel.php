<?php

namespace App\Enums;

enum NotificationChannel: string
{
    case Push = 'push';
    case Mejl = 'mejl';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
