<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Iniciran = 'iniciran';
    case Uspjesan = 'uspjesan';
    case Neuspjesan = 'neuspjesan';
    case Refundiran = 'refundiran';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
