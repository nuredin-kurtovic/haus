<?php

namespace Database\Seeders;

use App\Models\Technician;
use Illuminate\Database\Seeder;

class TechnicianSeeder extends Seeder
{
    public function run(): void
    {
        $technicians = [
            ['name' => 'Damir Hodžić', 'trade' => 'Vodoinstalater'],
            ['name' => 'Emir Begić', 'trade' => 'Elektroinstalater'],
            ['name' => 'Adnan Selimović', 'trade' => 'Grijanje i klima'],
            ['name' => 'Senad Karić', 'trade' => 'Bravar i stolar'],
        ];

        foreach ($technicians as $technician) {
            Technician::updateOrCreate(
                ['name' => $technician['name']],
                $technician + ['active' => true]
            );
        }
    }
}
