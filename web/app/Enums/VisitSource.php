<?php

namespace App\Enums;

enum VisitSource: string
{
    case Kredit = 'kredit';
    case Izlazak = 'izlazak';
    case Naplata = 'naplata';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
