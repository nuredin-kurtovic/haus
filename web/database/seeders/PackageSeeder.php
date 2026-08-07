<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'HAUS Mini',
                'slug' => 'haus-mini',
                'price_year' => 59.00,
                'visits_per_year' => 1,
                'deadline_hours' => 72,
                'emergency_deadline_hours' => 24,
                'emergency_included' => false,
                'labor_discount_pct' => 15,
                'material_discount_pct' => 0,
                'inspections_per_year' => 0,
                'warranty_months' => 6,
                'is_per_apartment' => false,
                'sort' => 1,
                'active' => true,
            ],
            [
                'name' => 'HAUS Plus',
                'slug' => 'haus-plus',
                'price_year' => 169.00,
                'visits_per_year' => 3,
                'deadline_hours' => 48,
                'emergency_deadline_hours' => 12,
                'emergency_included' => true,
                'labor_discount_pct' => 25,
                'material_discount_pct' => 5,
                'inspections_per_year' => 1,
                'warranty_months' => 12,
                'is_per_apartment' => false,
                'sort' => 2,
                'active' => true,
            ],
            [
                'name' => 'HAUS Pro',
                'slug' => 'haus-pro',
                'price_year' => 390.00,
                'visits_per_year' => 5,
                'deadline_hours' => 24,
                'emergency_deadline_hours' => 6,
                'emergency_included' => true,
                'labor_discount_pct' => 25,
                'material_discount_pct' => 5,
                'inspections_per_year' => 2,
                'warranty_months' => 12,
                'is_per_apartment' => true,
                'sort' => 3,
                'active' => true,
            ],
        ];

        foreach ($packages as $package) {
            Package::updateOrCreate(['slug' => $package['slug']], $package);
        }
    }
}
