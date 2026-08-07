<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PackageSeeder::class,
            CitySeeder::class,
            PriceListSeeder::class,
            SurchargeSeeder::class,
            SettingsSeeder::class,
            TechnicianSeeder::class,
            TestUserSeeder::class,
        ]);
    }
}
