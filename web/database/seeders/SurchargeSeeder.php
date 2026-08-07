<?php

namespace Database\Seeders;

use App\Enums\SurchargeType;
use App\Models\Surcharge;
use Illuminate\Database\Seeder;

class SurchargeSeeder extends Seeder
{
    public function run(): void
    {
        $surcharges = [
            ['key' => 'hitno_radni_dan', 'label' => 'Hitno u toku radnog dana', 'type' => SurchargeType::Percent, 'value' => 30],
            ['key' => 'radni_dan_vecer', 'label' => 'Radnim danom 18:00 do 22:00', 'type' => SurchargeType::Percent, 'value' => 40],
            ['key' => 'subota_poslije_14', 'label' => 'Subota poslije 14:00', 'type' => SurchargeType::Percent, 'value' => 40],
            ['key' => 'nedjelja_praznik', 'label' => 'Nedjelja i praznik', 'type' => SurchargeType::Percent, 'value' => 70],
            ['key' => 'noc', 'label' => 'Noć 22:00 do 07:00', 'type' => SurchargeType::Percent, 'value' => 100],
            ['key' => 'izvan_grada', 'label' => 'Izvan gradskog područja, po kilometru', 'type' => SurchargeType::PerKm, 'value' => 1.20],
            ['key' => 'uzaludan_izlazak', 'label' => 'Uzaludan izlazak', 'type' => SurchargeType::Flat, 'value' => 35],
        ];

        foreach ($surcharges as $sort => $surcharge) {
            $surcharge['sort'] = $sort + 1;
            $surcharge['active'] = true;

            Surcharge::updateOrCreate(['key' => $surcharge['key']], $surcharge);
        }
    }
}
