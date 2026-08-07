<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Termin izlaska: prozor je uvijek tacno 2 sata i mora stati u radno vrijeme.
 *
 * Radno vrijeme je podatak u bazi (settings.radno_vrijeme), nikad hardkodirano.
 * Nedjelja je zatvorena za sve osim hitnih naloga.
 */
class ScheduleService
{
    /** Prozor izlaska je uvijek tacno ovoliko minuta. */
    public const PROZOR_MINUTA = 120;

    /** @var array<int, string> */
    private const DANI = [
        1 => 'ponedjeljak',
        2 => 'utorak',
        3 => 'srijeda',
        4 => 'četvrtak',
        5 => 'petak',
        6 => 'subota',
        7 => 'nedjelja',
    ];

    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Naziv dana na bosanskom, za predlozak termin_potvrdjen.
     */
    public function danNaBosanskom(Carbon $datum): string
    {
        return self::DANI[$datum->isoWeekday()] ?? '';
    }

    /**
     * Prva greska u prozoru, ili null kad je prozor ispravan.
     */
    public function greskaProzora(Carbon $start, Carbon $end, bool $emergency = false): ?string
    {
        if ($end->lessThanOrEqualTo($start)) {
            return 'Kraj prozora mora biti poslije početka.';
        }

        if ((int) round($start->diffInMinutes($end, true)) !== self::PROZOR_MINUTA) {
            return 'Prozor izlaska mora trajati tačno 2 sata.';
        }

        if (! $start->isSameDay($end)) {
            return 'Prozor izlaska mora biti u istom danu.';
        }

        $dan = $start->isoWeekday();

        if ($dan === 7) {
            return $emergency
                ? null
                : 'Nedjeljom radimo samo hitne intervencije. Odaberite drugi dan.';
        }

        $radno = $dan === 6 ? $this->subota() : $this->ponPet();

        if ($this->minute($start) < $this->minuteIzTeksta($radno['od'])
            || $this->minute($end) > $this->minuteIzTeksta($radno['do'])) {
            return 'Termin je izvan radnog vremena. '
                .($dan === 6 ? 'Subotom radimo' : 'Radnim danom radimo')
                .' od '.$radno['od'].' do '.$radno['do'].'.';
        }

        return null;
    }

    /**
     * Radno vrijeme za prikaz i validaciju.
     *
     * @return array{od: string, do: string}
     */
    public function ponPet(): array
    {
        return $this->interval('pon_pet', '08:00', '18:00');
    }

    /**
     * @return array{od: string, do: string}
     */
    public function subota(): array
    {
        return $this->interval('subota', '09:00', '14:00');
    }

    /**
     * @return array{od: string, do: string}
     */
    private function interval(string $kljuc, string $od, string $do): array
    {
        $radno = $this->settings->get('radno_vrijeme', []);
        $dan = is_array($radno) && is_array($radno[$kljuc] ?? null) ? $radno[$kljuc] : [];

        return [
            'od' => (string) ($dan['od'] ?? $od),
            'do' => (string) ($dan['do'] ?? $do),
        ];
    }

    private function minute(Carbon $trenutak): int
    {
        return $trenutak->hour * 60 + $trenutak->minute;
    }

    private function minuteIzTeksta(string $vrijeme): int
    {
        [$sati, $minute] = array_pad(explode(':', $vrijeme), 2, '0');

        return ((int) $sati) * 60 + (int) $minute;
    }
}
