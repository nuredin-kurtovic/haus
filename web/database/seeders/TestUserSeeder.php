<?php

namespace Database\Seeders;

use App\Enums\PropertyUse;
use App\Enums\SubscriptionStatus;
use App\Models\City;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Nalozi za lokalni razvoj. Lozinka svima: haus1234.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $sarajevo = City::where('slug', 'sarajevo')->firstOrFail();
        $plus = Package::where('slug', 'haus-plus')->firstOrFail();
        $pro = Package::where('slug', 'haus-pro')->firstOrFail();

        $dispecer = $this->user('dispecer@haus.ba', 'Amra Sarić');
        $dispecer->syncRoles(['dispecer']);

        $klijent = $this->user('klijent@haus.ba', 'Amina Kovačević');
        $klijent->syncRoles(['klijent']);

        $plusSubscription = Subscription::updateOrCreate(
            ['user_id' => $klijent->id, 'package_id' => $plus->id],
            [
                'status' => SubscriptionStatus::Aktivna,
                'starts_at' => Carbon::now()->startOfDay(),
                'ends_at' => Carbon::now()->startOfDay()->addYear(),
                'auto_renew' => true,
                'price_paid' => $plus->price_year,
                'free_interventions' => 0,
            ]
        );

        SubscriptionProperty::updateOrCreate(
            ['subscription_id' => $plusSubscription->id, 'street' => 'Grbavička 12/3'],
            [
                'city_id' => $sarajevo->id,
                'use' => PropertyUse::Zivim,
                'remaining_visits' => 3,
                'remaining_inspections' => $plus->inspections_per_year,
            ]
        );

        $proKlijent = $this->user('pro@haus.ba', 'Vedad Alić');
        $proKlijent->syncRoles(['klijent']);

        $proSubscription = Subscription::updateOrCreate(
            ['user_id' => $proKlijent->id, 'package_id' => $pro->id],
            [
                'status' => SubscriptionStatus::Aktivna,
                'starts_at' => Carbon::now()->startOfDay(),
                'ends_at' => Carbon::now()->startOfDay()->addYear(),
                'auto_renew' => true,
                // 3 stana, popust na kolicinu 10 posto.
                'price_paid' => round((float) $pro->price_year * 3 * 0.90, 2),
                'free_interventions' => 0,
            ]
        );

        $stanovi = [
            ['street' => 'Hamdije Kreševljakovića 3/2', 'use' => PropertyUse::IzdajeSe, 'contact_name' => 'Lejla Musić', 'contact_note' => 'Ključ kod komšinice na drugom spratu.'],
            ['street' => 'Alipašina 47/1', 'use' => PropertyUse::IzdajeSe, 'contact_name' => 'Marija Perić', 'contact_note' => 'Gost je u stanu do kraja mjeseca.'],
            ['street' => 'Envera Šehovića 8/5', 'use' => PropertyUse::Prazan, 'contact_name' => null, 'contact_note' => 'Stan je prazan, ključ je u kancelariji.'],
        ];

        foreach ($stanovi as $stan) {
            SubscriptionProperty::updateOrCreate(
                ['subscription_id' => $proSubscription->id, 'street' => $stan['street']],
                [
                    'city_id' => $sarajevo->id,
                    'use' => $stan['use'],
                    'contact_name' => $stan['contact_name'],
                    'contact_note' => $stan['contact_note'],
                    'remaining_visits' => $pro->visits_per_year,
                    'remaining_inspections' => $pro->inspections_per_year,
                ]
            );
        }
    }

    private function user(string $email, string $name): User
    {
        return User::updateOrCreate(
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
    }
}
