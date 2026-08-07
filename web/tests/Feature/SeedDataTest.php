<?php

namespace Tests\Feature;

use App\Enums\SubscriptionStatus;
use App\Models\City;
use App\Models\Package;
use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\Setting;
use App\Models\Subscription;
use App\Models\Technician;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SeedDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_seed_puni_domenske_tabele(): void
    {
        $this->assertSame(3, Package::count());
        $this->assertSame(6, City::count());
        $this->assertSame(6, PriceCategory::count());
        $this->assertSame(58, PriceItem::count());
        $this->assertSame(4, Technician::count());
        $this->assertSame(9, Setting::count());
    }

    public function test_uloge_postoje(): void
    {
        foreach (['klijent', 'majstor', 'dispecer'] as $role) {
            $this->assertDatabaseHas('roles', ['name' => $role]);
        }
    }

    public function test_test_korisnici_i_pretplate(): void
    {
        $dispecer = User::where('email', 'dispecer@haus.ba')->firstOrFail();
        $this->assertTrue($dispecer->hasRole('dispecer'));
        $this->assertTrue(Hash::check('haus1234', $dispecer->password));

        $klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->assertTrue($klijent->hasRole('klijent'));

        $plus = $klijent->activeSubscription;
        $this->assertNotNull($plus);
        $this->assertSame(SubscriptionStatus::Aktivna, $plus->status);
        $this->assertSame('haus-plus', $plus->package->slug);
        $this->assertCount(1, $plus->properties);
        $this->assertSame(3, $plus->properties->first()->remaining_visits);
        $this->assertSame('Sarajevo', $plus->properties->first()->city->name);

        $pro = User::where('email', 'pro@haus.ba')->firstOrFail();
        $this->assertTrue($pro->hasRole('klijent'));

        $proSubscription = $pro->activeSubscription;
        $this->assertSame('haus-pro', $proSubscription->package->slug);
        $this->assertCount(3, $proSubscription->properties);

        foreach ($proSubscription->properties as $property) {
            $this->assertSame(5, $property->remaining_visits);
            $this->assertSame(2, $property->remaining_inspections);
            $this->assertSame('Sarajevo', $property->city->name);
        }
    }

    public function test_predlosci_obavjestenja_postoje_po_kljucevima(): void
    {
        $templates = Setting::where('key', 'notification_templates')->value('value');

        foreach ([
            'termin_potvrdjen', 'kasnjenje', 'rok_probijen', 'zavrseno',
            'prijava_primljena', 'majstor_krenuo', 'pretplata_aktivna', 'obnova_podsjetnik',
        ] as $key) {
            $this->assertArrayHasKey($key, $templates);
            $this->assertNotEmpty($templates[$key]);
        }
    }

    public function test_svaka_pretplata_ima_bar_jednu_adresu(): void
    {
        foreach (Subscription::with('properties')->get() as $subscription) {
            $this->assertGreaterThanOrEqual(1, $subscription->properties->count());
        }
    }

    public function test_nigdje_nema_em_dasha(): void
    {
        $roots = [base_path('app'), base_path('database'), base_path('routes')];
        $offenders = [];

        foreach ($roots as $root) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root));

            foreach ($files as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                if (str_contains((string) file_get_contents($file->getPathname()), "\u{2014}")) {
                    $offenders[] = $file->getPathname();
                }
            }
        }

        $this->assertSame([], $offenders, 'Em dash je pronadjen u: '.implode(', ', $offenders));
    }
}
