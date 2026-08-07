<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\PonudaZahtjevMail;
use App\Mail\UplatnicaMail;
use App\Models\City;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    private City $sarajevo;

    private City $zenica;

    /** Seed vec ima pretplate test korisnika, brojimo razliku. */
    private int $pretplateNaPocetku;

    private int $stanoviNaPocetku;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        $this->sarajevo = City::where('slug', 'sarajevo')->firstOrFail();
        // Zenica je u pripremi, registracija je ne smije primiti.
        $this->zenica = City::where('slug', 'zenica')->firstOrFail();

        $this->pretplateNaPocetku = Subscription::count();
        $this->stanoviNaPocetku = SubscriptionProperty::count();
    }

    private function novePretplate(): int
    {
        return Subscription::count() - $this->pretplateNaPocetku;
    }

    private function noviStanovi(): int
    {
        return SubscriptionProperty::count() - $this->stanoviNaPocetku;
    }

    private function paket(string $slug): Package
    {
        return Package::where('slug', $slug)->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'package_id' => $this->paket('haus-mini')->id,
            'name' => 'Emina Hodžić',
            'email' => 'emina@haus.ba',
            'password' => 'haus12345',
            'payment_method' => 'uplatnica',
            'properties' => [
                ['city_id' => $this->sarajevo->id, 'street' => 'Titova 15/2', 'use' => 'zivim'],
            ],
        ], $overrides);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function stanovi(int $broj): array
    {
        $stanovi = [];

        for ($i = 1; $i <= $broj; $i++) {
            $stanovi[] = [
                'city_id' => $this->sarajevo->id,
                'street' => 'Alipašina '.$i,
                'use' => 'izdaje_se',
                'contact_name' => 'Kontakt '.$i,
            ];
        }

        return $stanovi;
    }

    public function test_registracija_mini_uplatnicom_pravi_pretplatu_fakturu_i_salje_uplatnicu(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload());

        $response->assertCreated()
            ->assertJsonStructure([
                'status',
                'user' => ['id', 'name', 'email'],
                'subscription' => ['id', 'status', 'package', 'starts_at', 'ends_at', 'price', 'properties'],
                'token',
                'invoice' => ['id', 'number', 'type', 'status', 'total'],
            ])
            ->assertJsonPath('status', 'cekanje_uplate')
            ->assertJsonPath('subscription.status', 'cekanje_uplate')
            ->assertJsonPath('subscription.starts_at', null)
            ->assertJsonPath('subscription.ends_at', null)
            ->assertJsonPath('subscription.price', 59)
            ->assertJsonPath('subscription.remaining_visits', 1)
            ->assertJsonPath('invoice.total', 59)
            ->assertJsonPath('invoice.status', 'nenaplaceno')
            ->assertJsonPath('invoice.type', 'pretplata');

        // Bez kartice nema payment objekta.
        $this->assertArrayNotHasKey('payment', $response->json());
        $this->assertNotEmpty($response->json('token'));

        $user = User::where('email', 'emina@haus.ba')->firstOrFail();
        $this->assertTrue($user->hasRole('klijent'));

        $subscription = Subscription::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(SubscriptionStatus::CekanjeUplate, $subscription->status);
        $this->assertNull($subscription->starts_at);
        $this->assertNull($subscription->ends_at);
        $this->assertNull($subscription->price_paid);
        $this->assertCount(1, $subscription->properties);

        $property = $subscription->properties->first();
        $this->assertSame(1, $property->remaining_visits);
        $this->assertSame(0, $property->remaining_inspections);
        $this->assertSame($this->sarajevo->id, $property->city_id);
        $this->assertSame('Titova 15/2', $property->street);

        $invoice = Invoice::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(InvoiceType::Pretplata, $invoice->type);
        $this->assertSame(InvoiceStatus::Nenaplaceno, $invoice->status);
        $this->assertEqualsWithDelta(59, (float) $invoice->total, 0.001);
        $this->assertNotNull($invoice->sent_at);

        $this->assertSame(0, Payment::count());

        Mail::assertQueued(UplatnicaMail::class, function (UplatnicaMail $mail) use ($invoice, $user) {
            return $mail->invoice->is($invoice) && $mail->hasTo($user->email);
        });
    }

    public function test_izdati_token_odmah_radi_iako_pretplata_ceka_uplatu(): void
    {
        $token = $this->postJson('/api/v1/auth/register', $this->payload())->json('token');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('role', 'klijent')
            // Pretplata jos nije aktivna, pa sazetak nema sta pokazati.
            ->assertJsonPath('subscription', null);
    }

    public function test_broj_fakture_je_poziv_na_broj_i_raste(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();
        $this->postJson('/api/v1/auth/register', $this->payload(['email' => 'drugi@haus.ba']))->assertCreated();

        $brojevi = Invoice::orderBy('id')->pluck('number')->all();

        $godina = (string) now()->year;

        $this->assertSame([$godina.'000001', $godina.'000002'], $brojevi);
    }

    public function test_registracija_plus_paketom_racuna_fiksnu_cijenu(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'package_id' => $this->paket('haus-plus')->id,
        ]))->assertCreated()
            ->assertJsonPath('subscription.price', 169)
            ->assertJsonPath('subscription.remaining_visits', 3)
            ->assertJsonPath('subscription.remaining_inspections', 1)
            ->assertJsonPath('invoice.total', 169);
    }

    public function test_registracija_odbija_grad_u_pripremi(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'properties' => [
                ['city_id' => $this->zenica->id, 'street' => 'Neka ulica 1'],
            ],
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('properties.0.city_id');

        $this->assertSame(
            'U tom gradu još ne radimo. Odaberite grad sa liste.',
            $response->json('errors.properties\\.0\\.city_id.0') ?? $response->json('errors')['properties.0.city_id'][0]
        );

        $this->assertSame(0, $this->novePretplate());
        $this->assertNull(User::where('email', 'emina@haus.ba')->first());
    }

    public function test_registracija_odbija_nepostojeci_grad(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'properties' => [['city_id' => 99999, 'street' => 'Neka ulica 1']],
        ]))->assertStatus(422)->assertJsonValidationErrors('properties.0.city_id');
    }

    public function test_registracija_odbija_pro_sa_jednim_stanom(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'package_id' => $this->paket('haus-pro')->id,
            'properties' => $this->stanovi(1),
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('properties');

        $this->assertStringContainsString('najmanje 2 stana', $response->json('errors.properties.0'));
        $this->assertSame(0, $this->novePretplate());
    }

    public function test_registracija_odbija_mini_sa_dvije_adrese(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'properties' => $this->stanovi(2),
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('properties');

        $this->assertStringContainsString('tačno jednu adresu', $response->json('errors.properties.0'));
    }

    public function test_pro_sa_tri_stana_ima_popust_10_posto(): void
    {
        // 390 x 3 = 1170, popust 10 posto daje 1053.
        $this->postJson('/api/v1/auth/register', $this->payload([
            'package_id' => $this->paket('haus-pro')->id,
            'properties' => $this->stanovi(3),
        ]))->assertCreated()
            ->assertJsonPath('subscription.price', 1053)
            ->assertJsonPath('invoice.total', 1053)
            ->assertJsonPath('subscription.remaining_visits', 15)
            ->assertJsonPath('subscription.remaining_inspections', 6);

        $this->assertSame(3, $this->noviStanovi());
    }

    public function test_pro_sa_sest_stanova_ima_popust_15_posto(): void
    {
        // 390 x 6 = 2340, popust 15 posto daje 1989.
        $this->postJson('/api/v1/auth/register', $this->payload([
            'package_id' => $this->paket('haus-pro')->id,
            'properties' => $this->stanovi(6),
        ]))->assertCreated()
            ->assertJsonPath('subscription.price', 1989)
            ->assertJsonPath('invoice.total', 1989);
    }

    public function test_pro_sa_dva_stana_ima_popust_10_posto(): void
    {
        // 390 x 2 = 780, popust 10 posto daje 702.
        $this->postJson('/api/v1/auth/register', $this->payload([
            'package_id' => $this->paket('haus-pro')->id,
            'properties' => $this->stanovi(2),
        ]))->assertCreated()->assertJsonPath('subscription.price', 702);
    }

    public function test_pro_sa_deset_stanova_postaje_ponuda_bez_fakture(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'package_id' => $this->paket('haus-pro')->id,
            'payment_method' => 'kartica',
            'properties' => $this->stanovi(10),
        ]));

        $response->assertCreated()
            ->assertJsonPath('status', 'ponuda')
            ->assertJsonPath('subscription.status', 'ponuda');

        $this->assertArrayNotHasKey('invoice', $response->json());
        $this->assertArrayNotHasKey('payment', $response->json());
        $this->assertNotEmpty($response->json('token'));

        $this->assertSame(0, Invoice::count());
        $this->assertSame(0, Payment::count());
        $this->assertSame(10, $this->noviStanovi());

        $subscription = Subscription::latest('id')->firstOrFail();
        $this->assertSame(SubscriptionStatus::Ponuda, $subscription->status);
        $this->assertNull($subscription->starts_at);

        // Ponuda ne salje uplatnicu ni racun, samo zahtjev dispeceru.
        Mail::assertNotQueued(UplatnicaMail::class);
        Mail::assertNothingSent();
    }

    public function test_ponuda_salje_mejl_dispeceru(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload([
            'package_id' => $this->paket('haus-pro')->id,
            'properties' => $this->stanovi(12),
        ]))->assertCreated();

        Mail::assertQueued(PonudaZahtjevMail::class, function (PonudaZahtjevMail $mail) {
            return $mail->hasTo(config('services.haus.dispatcher_email'));
        });

        Mail::assertNotQueued(UplatnicaMail::class);
    }

    public function test_registracija_karticom_vraca_redirect_i_pravi_iniciranu_uplatu(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'payment_method' => 'kartica',
        ]));

        $response->assertCreated()
            ->assertJsonStructure(['payment' => ['redirect_url']])
            ->assertJsonPath('status', 'cekanje_uplate');

        $redirect = $response->json('payment.redirect_url');

        $this->assertStringContainsString('/placanje/simulacija?ref=FAKE-', $redirect);

        $payment = Payment::firstOrFail();

        $this->assertSame(PaymentStatus::Iniciran, $payment->status);
        $this->assertSame('kartica', $payment->method->value);
        $this->assertStringStartsWith('FAKE-', (string) $payment->gateway_reference);
        $this->assertEqualsWithDelta(59, (float) $payment->amount, 0.001);
        $this->assertStringContainsString($payment->gateway_reference, $redirect);

        // Kartica ne salje uplatnicu.
        Mail::assertNotQueued(UplatnicaMail::class);
    }

    public function test_registracija_odbija_zauzet_mejl(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'email' => 'klijent@haus.ba',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame(
            'Ova mejl adresa je već registrovana. Prijavite se.',
            $response->json('errors.email.0')
        );
    }

    public function test_registracija_odbija_kratku_lozinku(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'password' => 'kratka',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('password');

        $this->assertSame('Lozinka mora imati najmanje 8 znakova.', $response->json('errors.password.0'));
    }

    public function test_registracija_odbija_nepoznat_nacin_placanja(): void
    {
        $response = $this->postJson('/api/v1/auth/register', $this->payload([
            'payment_method' => 'gotovina',
        ]));

        $response->assertStatus(422)->assertJsonValidationErrors('payment_method');

        $this->assertSame(
            'Način plaćanja može biti uplatnica ili kartica.',
            $response->json('errors.payment_method.0')
        );
    }

    public function test_registracija_odbija_neaktivan_paket(): void
    {
        $mini = $this->paket('haus-mini');
        $mini->update(['active' => false]);

        $this->postJson('/api/v1/auth/register', $this->payload(['package_id' => $mini->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('package_id');
    }

    public function test_prijava_radi_odmah_nakon_registracije(): void
    {
        $this->postJson('/api/v1/auth/register', $this->payload())->assertCreated();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'emina@haus.ba',
            'password' => 'haus12345',
        ])->assertOk()->assertJsonPath('role', 'klijent');
    }
}
