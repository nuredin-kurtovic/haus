<?php

namespace Tests\Feature\Api;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\RacunMail;
use App\Models\City;
use App\Models\Invoice;
use App\Models\NotificationLog;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentToken;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payments\FakeGateway;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaymentWebhookTest extends TestCase
{
    use RefreshDatabase;

    private City $sarajevo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        $this->sarajevo = City::where('slug', 'sarajevo')->firstOrFail();
    }

    /**
     * Registruje klijenta karticom i vraca iniciranu uplatu.
     */
    private function iniciranaUplata(string $email = 'novi@haus.ba'): Payment
    {
        $this->postJson('/api/v1/auth/register', [
            'package_id' => Package::where('slug', 'haus-plus')->value('id'),
            'name' => 'Selma Begić',
            'email' => $email,
            'password' => 'haus12345',
            'payment_method' => 'kartica',
            'properties' => [
                ['city_id' => $this->sarajevo->id, 'street' => 'Zmaja od Bosne 4'],
            ],
        ])->assertCreated();

        return Payment::query()->latest('id')->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function webhook(array $payload, ?string $signature = null): TestResponse
    {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $signature ??= app(FakeGateway::class)->signPayload($body);

        return $this->call(
            'POST',
            '/api/v1/webhooks/monri',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
                'HTTP_X_FAKE_SIGNATURE' => $signature,
            ],
            $body
        );
    }

    public function test_approved_webhook_aktivira_pretplatu_i_knjizi_fakturu(): void
    {
        $payment = $this->iniciranaUplata();
        $invoice = $payment->invoice;
        $subscription = $invoice->subscription;

        $this->assertSame(SubscriptionStatus::CekanjeUplate, $subscription->status);

        $this->webhook([
            'reference' => $payment->gateway_reference,
            'status' => 'approved',
            'masked_pan' => '411111xxxxxx1111',
            'token' => 'mit-token-123',
        ])->assertOk()->assertJsonPath('status', 'uspjesan')->assertJsonPath('processed', true);

        $payment->refresh();
        $invoice->refresh();
        $subscription->refresh();

        $this->assertSame(PaymentStatus::Uspjesan, $payment->status);

        $this->assertSame(InvoiceStatus::Placeno, $invoice->status);
        $this->assertNotNull($invoice->paid_at);

        $this->assertSame(SubscriptionStatus::Aktivna, $subscription->status);
        $this->assertNotNull($subscription->starts_at);
        $this->assertNotNull($subscription->ends_at);
        // Pretplata traje tacno godinu dana od aktivacije.
        $this->assertSame(
            $subscription->starts_at->copy()->addYear()->toIso8601String(),
            $subscription->ends_at->toIso8601String()
        );
        $this->assertEqualsWithDelta(169, (float) $subscription->price_paid, 0.001);

        // Token za MIT obnovu je spremljen.
        $token = PaymentToken::where('user_id', $invoice->user_id)->firstOrFail();
        $this->assertSame('mit-token-123', $token->token);
        $this->assertSame('411111xxxxxx1111', $token->masked_pan);
        $this->assertTrue($token->active);

        Mail::assertQueued(RacunMail::class, fn (RacunMail $mail) => $mail->invoice->is($invoice));

        $log = NotificationLog::where('user_id', $invoice->user_id)
            ->where('template_key', 'pretplata_aktivna')
            ->get();

        $this->assertCount(2, $log, 'Ocekujemo red za mejl i red za push.');
        $this->assertEqualsCanonicalizing(['mejl', 'push'], $log->pluck('channel')->map->value->all());
        $this->assertStringContainsString('HAUS Plus', $log->first()->body);
        $this->assertStringNotContainsString('{paket}', $log->first()->body);
        $this->assertStringContainsString($subscription->ends_at->format('d.m.Y').'.', $log->first()->body);
        // Predlozak daje tacku iza datuma, ne smije ih biti dvije.
        $this->assertStringNotContainsString('..', $log->first()->body);
    }

    public function test_approved_webhook_je_idempotentan(): void
    {
        $payment = $this->iniciranaUplata();

        $payload = ['reference' => $payment->gateway_reference, 'status' => 'approved'];

        $this->webhook($payload)->assertOk()->assertJsonPath('processed', true);

        $subscription = $payment->invoice->subscription;
        $subscription->refresh();

        $prviStart = $subscription->starts_at->toIso8601String();

        $this->webhook($payload)->assertOk()->assertJsonPath('processed', false);

        $subscription->refresh();

        $this->assertSame(SubscriptionStatus::Aktivna, $subscription->status);
        $this->assertSame($prviStart, $subscription->starts_at->toIso8601String());

        // Racun i obavjestenje idu tacno jednom.
        Mail::assertQueuedCount(2); // uplatnica nije slana, ovdje su racun i obavjestenje
        $this->assertSame(2, NotificationLog::where('template_key', 'pretplata_aktivna')->count());
    }

    public function test_declined_webhook_ne_aktivira_nista(): void
    {
        $payment = $this->iniciranaUplata();

        $this->webhook([
            'reference' => $payment->gateway_reference,
            'status' => 'declined',
        ])->assertOk()->assertJsonPath('status', 'neuspjesan');

        $payment->refresh();
        $invoice = $payment->invoice()->first();
        $subscription = $invoice->subscription()->first();

        $this->assertSame(PaymentStatus::Neuspjesan, $payment->status);
        $this->assertSame(InvoiceStatus::Nenaplaceno, $invoice->status);
        $this->assertNull($invoice->paid_at);
        $this->assertSame(SubscriptionStatus::CekanjeUplate, $subscription->status);
        $this->assertNull($subscription->starts_at);

        $this->assertSame(0, PaymentToken::count());
        $this->assertSame(0, NotificationLog::count());

        Mail::assertNotQueued(RacunMail::class);
    }

    public function test_webhook_sa_losim_potpisom_vraca_403(): void
    {
        $payment = $this->iniciranaUplata();

        $this->webhook(
            ['reference' => $payment->gateway_reference, 'status' => 'approved'],
            signature: 'ovo-nije-potpis'
        )->assertStatus(403)->assertJsonPath('message', 'Potpis nije ispravan.');

        $payment->refresh();

        $this->assertSame(PaymentStatus::Iniciran, $payment->status);
        $this->assertSame(SubscriptionStatus::CekanjeUplate, $payment->invoice->subscription->status);
    }

    public function test_webhook_bez_potpisa_vraca_403(): void
    {
        $payment = $this->iniciranaUplata();

        $this->postJson('/api/v1/webhooks/monri', [
            'reference' => $payment->gateway_reference,
            'status' => 'approved',
        ])->assertStatus(403);
    }

    public function test_webhook_sa_nepoznatom_referencom_vraca_404(): void
    {
        $this->webhook(['reference' => 'FAKE-nepostojeca', 'status' => 'approved'])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Uplata nije pronađena.');
    }

    public function test_webhook_odbija_nepoznat_status(): void
    {
        $payment = $this->iniciranaUplata();

        $this->webhook(['reference' => $payment->gateway_reference, 'status' => 'mozda'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');
    }

    public function test_aktivirana_pretplata_se_vidi_na_me(): void
    {
        $payment = $this->iniciranaUplata();

        $this->webhook(['reference' => $payment->gateway_reference, 'status' => 'approved'])->assertOk();

        $user = User::where('email', 'novi@haus.ba')->firstOrFail();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('subscription.status', 'aktivna')
            ->assertJsonPath('subscription.package.slug', 'haus-plus')
            ->assertJsonPath('subscription.remaining_visits', 3);

        $this->assertNotNull(Subscription::where('user_id', $user->id)->value('ends_at'));
        $this->assertSame(InvoiceStatus::Placeno, Invoice::where('user_id', $user->id)->first()->status);
    }
}
