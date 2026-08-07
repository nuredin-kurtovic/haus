<?php

namespace Database\Seeders;

use App\Enums\CityStatus;
use App\Models\City;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CitySeeder extends Seeder
{
    public function run(): void
    {
        $cities = [
            ['name' => 'Sarajevo', 'lat' => 43.8563000, 'lng' => 18.4131000, 'status' => CityStatus::Aktivan],
            ['name' => 'Travnik', 'lat' => 44.2264000, 'lng' => 17.6657000, 'status' => CityStatus::Aktivan],
            ['name' => 'Zenica', 'lat' => 44.2014000, 'lng' => 17.9067000, 'status' => CityStatus::UPripremi],
            ['name' => 'Mostar', 'lat' => 43.3438000, 'lng' => 17.8078000, 'status' => CityStatus::UPripremi],
            ['name' => 'Tuzla', 'lat' => 44.5384000, 'lng' => 18.6739000, 'status' => CityStatus::UPripremi],
            ['name' => 'Bihać', 'lat' => 44.8169000, 'lng' => 15.8708000, 'status' => CityStatus::UPripremi],
        ];

        foreach ($cities as $city) {
            $city['slug'] = Str::slug($city['name']);

            City::updateOrCreate(['slug' => $city['slug']], $city);
        }
    }
}
