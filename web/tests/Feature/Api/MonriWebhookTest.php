<?php

namespace Tests\Feature\Api;

use App\Contracts\PaymentGateway;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\RacunMail;
use App\Models\City;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentToken;
use App\Services\Payments\MonriGateway;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Isti webhook, ali sa Monri driverom i Monri poljima. Potpisan callback
 * aktivira pretplatu, nepotpisan ne prolazi ni do baze.
 */
class MonriWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'tajni-kljuc-trgovca';

    private City $sarajevo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        config()->set('services.haus.payment_gateway', 'monri');
        config()->set('services.monri.key', self::KEY);
        config()->set('services.monri.authenticity_token', 'auth-token-123');

        // Driver je singleton, pa se veza mora zaboraviti poslije promjene.
        $this->app->forgetInstance(PaymentGateway::class);

        $this->sarajevo = City::where('slug', 'sarajevo')->firstOrFail();
    }

    public function test_potpisan_monri_callback_aktivira_pretplatu(): void
    {
        $payment = $this->iniciranaUplata();
        $invoice = $payment->invoice;

        // Referenca prema Monriju je broj fakture, on je i order_number.
        $this->assertSame($invoice->number, $payment->gateway_reference);
        $this->assertSame(SubscriptionStatus::CekanjeUplate, $invoice->subscription->status);

        $this->posaljiCallback([
            'order_number' => $invoice->number,
            'status' => 'approved',
            'amount' => 16900,
            'currency' => 'BAM',
            'pan_token' => 'monri-token-abc',
            'masked_pan' => '403940xxxxxx1881',
        ])->assertOk()->assertJsonPath('status', 'uspjesan')->assertJsonPath('processed', true);

        $payment->refresh();
        $invoice->refresh();

        $this->assertSame(PaymentStatus::Uspjesan, $payment->status);
        $this->assertSame(InvoiceStatus::Placeno, $invoice->status);

        $subscription = $invoice->subscription()->first();
        $this->assertSame(SubscriptionStatus::Aktivna, $subscription->status);
        $this->assertNotNull($subscription->starts_at);
        $this->assertNotNull($subscription->ends_at);

        // pan_token iz Monri odgovora je token za MIT obnovu.
        $token = PaymentToken::where('user_id', $invoice->user_id)->firstOrFail();
        $this->assertSame('monri-token-abc', $token->token);
        $this->assertSame('403940xxxxxx1881', $token->masked_pan);

        Mail::assertQueued(RacunMail::class);
    }

    public function test_odbijen_monri_callback_ne_aktivira_nista(): void
    {
        $payment = $this->iniciranaUplata();

        $this->posaljiCallback([
            'order_number' => $payment->invoice->number,
            'status' => 'declined',
            'response_code' => '51',
        ])->assertOk()->assertJsonPath('status', 'neuspjesan');

        $this->assertSame(PaymentStatus::Neuspjesan, $payment->refresh()->status);
        $this->assertSame(
            SubscriptionStatus::CekanjeUplate,
            $payment->invoice->subscription()->first()->status
        );
        $this->assertSame(0, PaymentToken::count());
    }

    public function test_monri_callback_bez_potpisa_vraca_403(): void
    {
        $payment = $this->iniciranaUplata();

        $this->postJson('/api/v1/webhooks/monri', [
            'order_number' => $payment->invoice->number,
            'status' => 'approved',
        ])->assertStatus(403);

        $this->assertSame(PaymentStatus::Iniciran, $payment->refresh()->status);
    }

    public function test_monri_callback_sa_podmetnutim_potpisom_vraca_403(): void
    {
        $payment = $this->iniciranaUplata();

        $this->posaljiCallback(
            ['order_number' => $payment->invoice->number, 'status' => 'approved'],
            potpis: 'WP3-callback '.hash('sha512', 'nije-nas-kljuc').' 1786000000'
        )->assertStatus(403);

        $this->assertSame(PaymentStatus::Iniciran, $payment->refresh()->status);
    }

    public function test_monri_callback_bez_broja_narudzbe_pada_na_validaciji(): void
    {
        $this->iniciranaUplata();

        $this->posaljiCallback(['status' => 'approved'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('order_number');
    }

    public function test_monri_callback_sa_nepoznatim_brojem_vraca_404(): void
    {
        $this->iniciranaUplata();

        $this->posaljiCallback(['order_number' => '2026999999', 'status' => 'approved'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Uplata nije pronađena.');
    }

    public function test_registracija_vraca_polja_monri_forme(): void
    {
        $odgovor = $this->registruj();

        $odgovor->assertJsonPath('payment.method', 'POST');
        $odgovor->assertJsonPath('payment.fields.currency', 'BAM');
        $odgovor->assertJsonPath('payment.fields.amount', '16900');
        $odgovor->assertJsonPath('payment.fields.tokenize_pan_offered', '1');
        $this->assertStringContainsString('/v2/form', $odgovor->json('payment.redirect_url'));
    }

    private function registruj(string $email = 'novi@haus.ba'): TestResponse
    {
        return $this->postJson('/api/v1/auth/register', [
            'package_id' => Package::where('slug', 'haus-plus')->value('id'),
            'name' => 'Selma Begić',
            'email' => $email,
            'password' => 'haus12345',
            'payment_method' => 'kartica',
            'properties' => [
                ['city_id' => $this->sarajevo->id, 'street' => 'Zmaja od Bosne 4'],
            ],
        ])->assertCreated();
    }

    private function iniciranaUplata(): Payment
    {
        $this->registruj();

        return Payment::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function posaljiCallback(array $payload, ?string $potpis = null): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = '1786000000';

        $potpis ??= 'WP3-callback '.app(MonriGateway::class)->signCallback($body, $timestamp).' '.$timestamp;

        return $this->call('POST', '/api/v1/webhooks/monri', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_AUTHORIZATION' => $potpis,
        ], $body);
    }
}
