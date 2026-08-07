<?php

namespace Database\Seeders;

use App\Services\SettingsService;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = app(SettingsService::class);

        $values = [
            'radno_vrijeme' => [
                'pon_pet' => ['od' => '08:00', 'do' => '18:00'],
                'subota' => ['od' => '09:00', 'do' => '14:00'],
                'nedjelja' => ['samo_hitno' => true],
                'napomena' => 'Nedjeljom radimo samo hitne intervencije.',
            ],
            'satnica_redovna' => 40,
            'satnica_hitna' => 70,
            'izlazak_bez_pretplate' => 35,
            'ukljuceno_minuta' => 45,
            'materijal_marza_pct' => 20,
            'pro_volume_tiers' => [
                ['min' => 2, 'max' => 4, 'pct' => 10],
                ['min' => 5, 'max' => 9, 'pct' => 15],
            ],
            'price_list_version' => 1,
            'notification_templates' => $this->templates(),
        ];

        foreach ($values as $key => $value) {
            $settings->set($key, $value);
        }
    }

    /**
     * Ton po specu: kratke rečenice, uvijek Vi, konkretno vrijeme, bez em dasha.
     *
     * @return array<string, string>
     */
    private function templates(): array
    {
        return [
            'prijava_primljena' => 'HAUS: Prijava je primljena. Nalog {broj}. Rok izlaska je {rok}. Termin javljamo čim ga potvrdimo.',
            'termin_potvrdjen' => 'HAUS: Termin potvrđen. {dan} {datum}, između {od} i {do}. Majstor: {majstor}. Otkazivanje u aplikaciji.',
            'majstor_krenuo' => 'HAUS: Majstor {majstor} je krenuo. Kod vas je do {do}. Nalog {broj}.',
            'kasnjenje' => 'HAUS: Kasnimo {minuta} min, prethodni nalog se otegao. Novo vrijeme: {novo_vrijeme}. Izvinjavamo se.',
            'rok_probijen' => 'HAUS: Nismo ispunili obećani rok. Vaša sljedeća intervencija je besplatna, već je upisana.',
            'zavrseno' => 'HAUS: Sređeno. Garancija na rad do {garancija_datum}. Nalaz i fotografije su u vašem kartonu.',
            'pretplata_aktivna' => 'HAUS: Pretplata je aktivna. Paket {paket}, vrijedi do {vrijedi_do}. Prvu prijavu možete poslati odmah.',
            'obnova_podsjetnik' => 'HAUS: Pretplata ističe {datum}. Obnova je automatska, iznos je {iznos} KM. Otkazivanje je jedan klik u profilu.',
        ];
    }
}
