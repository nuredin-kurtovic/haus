<?php

namespace App\Enums;

enum JobStatus: string
{
    case Novo = 'novo';
    case Zakazano = 'zakazano';
    case UToku = 'u_toku';
    case Zavrseno = 'zavrseno';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
