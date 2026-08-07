<?php

namespace Database\Seeders;

use App\Models\Technician;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Majstori. Svaki ima i korisnicki nalog sa ulogom majstor, jer je serviser
 * punopravan mobilni korisnik. Lozinka svima: haus1234.
 */
class TechnicianSeeder extends Seeder
{
    public function run(): void
    {
        $technicians = [
            ['name' => 'Damir Hodžić', 'trade' => 'Vodoinstalater', 'email' => 'damir@haus.ba'],
            ['name' => 'Emir Begić', 'trade' => 'Elektroinstalater', 'email' => 'emir@haus.ba'],
            ['name' => 'Adnan Selimović', 'trade' => 'Grijanje i klima', 'email' => 'adnan@haus.ba'],
            ['name' => 'Senad Karić', 'trade' => 'Bravar i stolar', 'email' => 'senad@haus.ba'],
        ];

        foreach ($technicians as $technician) {
            $user = $this->korisnik($technician['email'], $technician['name']);

            Technician::updateOrCreate(
                ['name' => $technician['name']],
                [
                    'trade' => $technician['trade'],
                    'user_id' => $user->id,
                    'active' => true,
                ]
            );
        }
    }

    private function korisnik(string $email, string $name): User
    {
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('haus1234'),
                'email_verified_at' => Carbon::now(),
                'notif_push' => true,
                'notif_email' => true,
                'notif_marketing' => false,
            ]
        );

        $user->syncRoles(['majstor']);

        return $user;
    }
}
