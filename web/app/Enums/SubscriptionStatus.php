<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case CekanjeUplate = 'cekanje_uplate';
    case Aktivna = 'aktivna';
    case Istekla = 'istekla';
    case Otkazana = 'otkazana';
    case Ponuda = 'ponuda';

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
