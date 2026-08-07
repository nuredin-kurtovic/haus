<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\RacunMail;
use App\Models\City;
use App\Models\NotificationLog;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentToken;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class DevFakePaymentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        config()->set('services.haus.payment_gateway', 'fake');
        config()->set('app.debug', true);
    }

    private function iniciranaUplata(string $email = 'novi@haus.ba'): Payment
    {
        $this->postJson('/api/v1/auth/register', [
            'package_id' => Package::where('slug', 'haus-plus')->value('id'),
            'name' => 'Selma Begić',
            'email' => $email,
            'password' => 'haus12345',
            'payment_method' => 'kartica',
            'properties' => [
                ['city_id' => City::where('slug', 'sarajevo')->value('id'), 'street' => 'Zmaja od Bosne 4'],
            ],
        ])->assertCreated();

        return Payment::query()->latest('id')->firstOrFail();
    }

    public function test_approved_simulacija_aktivira_pretplatu(): void
    {
        $payment = $this->iniciranaUplata();

        $this->postJson('/api/v1/dev/fake-payment', [
            'reference' => $payment->gateway_reference,
            'outcome' => 'approved',
        ])
            ->assertOk()
            ->assertJsonPath('processed', true)
            ->assertJsonPath('subscription_status', 'aktivna');

        $payment->refresh();
        $invoice = $payment->invoice()->first();

        $this->assertSame(PaymentStatus::Uspjesan, $payment->status);
        $this->assertSame(InvoiceStatus::Placeno, $invoice->status);
        $this->assertSame(SubscriptionStatus::Aktivna, $invoice->subscription()->first()->status);

        // Isti kod put kao pravi webhook: racun, obavjestenje i token za obnovu.
        Mail::assertQueued(RacunMail::class);
        $this->assertSame(2, NotificationLog::where('template_key', 'pretplata_aktivna')->count());

        $token = PaymentToken::where('user_id', $invoice->user_id)->firstOrFail();
        $this->assertSame('403940xxxxxx1881', $token->masked_pan);
        $this->assertStringStartsWith('FAKE-TOKEN-', $token->token);
        $this->assertTrue($token->active);
    }

    public function test_approved_simulacija_je_idempotentna(): void
    {
        $payment = $this->iniciranaUplata();

        $payload = ['reference' => $payment->gateway_reference, 'outcome' => 'approved'];

        $this->postJson('/api/v1/dev/fake-payment', $payload)
            ->assertOk()
            ->assertJsonPath('processed', true);

        $this->postJson('/api/v1/dev/fake-payment', $payload)
            ->assertOk()
            ->assertJsonPath('processed', false)
            ->assertJsonPath('subscription_status', 'aktivna');

        $this->assertSame(2, NotificationLog::where('template_key', 'pretplata_aktivna')->count());
    }

    public function test_declined_simulacija_ne_aktivira_pretplatu(): void
    {
        $payment = $this->iniciranaUplata();

        $this->postJson('/api/v1/dev/fake-payment', [
            'reference' => $payment->gateway_reference,
            'outcome' => 'declined',
        ])
            ->assertOk()
            ->assertJsonPath('subscription_status', 'cekanje_uplate');

        $this->assertSame(PaymentStatus::Neuspjesan, $payment->refresh()->status);
        $this->assertSame(0, PaymentToken::count());
    }

    public function test_ruta_ne_postoji_kad_gateway_nije_fake(): void
    {
        $payment = $this->iniciranaUplata();

        config()->set('services.haus.payment_gateway', 'monri');

        $this->postJson('/api/v1/dev/fake-payment', [
            'reference' => $payment->gateway_reference,
            'outcome' => 'approved',
        ])->assertStatus(404);

        $this->assertSame(PaymentStatus::Iniciran, $payment->refresh()->status);
    }

    public function test_ruta_ne_postoji_bez_debuga(): void
    {
        $payment = $this->iniciranaUplata();

        config()->set('app.debug', false);

        $this->postJson('/api/v1/dev/fake-payment', [
            'reference' => $payment->gateway_reference,
            'outcome' => 'approved',
        ])->assertStatus(404);
    }

    public function test_nepoznata_referenca_vraca_404(): void
    {
        $this->postJson('/api/v1/dev/fake-payment', [
            'reference' => 'FAKE-nepostojeca',
            'outcome' => 'approved',
        ])->assertStatus(404)->assertJsonPath('message', 'Uplata nije pronađena.');
    }

    public function test_nepoznat_ishod_se_odbija(): void
    {
        $payment = $this->iniciranaUplata();

        $this->postJson('/api/v1/dev/fake-payment', [
            'reference' => $payment->gateway_reference,
            'outcome' => 'mozda',
        ])->assertStatus(422)->assertJsonValidationErrors('outcome');
    }

    public function test_simulacija_ne_trazi_prijavu(): void
    {
        $payment = $this->iniciranaUplata();

        // Stranica simulacije radi prije nego korisnik ima aktivnu pretplatu.
        $this->postJson('/api/v1/dev/fake-payment', [
            'reference' => $payment->gateway_reference,
            'outcome' => 'approved',
        ])->assertOk();
    }
}
