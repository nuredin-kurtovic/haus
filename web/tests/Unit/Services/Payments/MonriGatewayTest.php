<?php

namespace Tests\Unit\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentToken;
use App\Models\User;
use App\Services\Payments\MonriGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * MonriGateway bez mreze: digest, potpis callbacka, refund i MIT naplata.
 *
 * Pretpostavke o Monri oblicima su u config/services.php i u TODO MONRI
 * komentarima u razredu. Ovi testovi cuvaju da se ponasanje ne promijeni
 * slucajno, a ne da je Monri ugovor tacan.
 */
class MonriGatewayTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'tajni-kljuc-trgovca';

    private MonriGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.monri.key', self::KEY);
        config()->set('services.monri.authenticity_token', 'auth-token-123');
        config()->set('services.monri.api_base', 'https://ipgtest.monri.com');
        config()->set('services.monri.webpay_base', 'https://ipgtest.monri.com');
        config()->set('services.monri.success_url', 'https://haus.ba/placanje/uspjeh');
        config()->set('services.monri.callback_url', 'https://haus.ba/api/v1/webhooks/monri');

        $this->gateway = new MonriGateway;
    }

    public function test_digest_je_sha512_kljuca_broja_iznosa_i_valute(): void
    {
        $ocekivano = hash('sha512', self::KEY.'2026000001'.'16900'.'BAM');

        $this->assertSame($ocekivano, $this->gateway->formDigest('2026000001', 16900, 'BAM'));
        $this->assertSame(128, strlen($this->gateway->formDigest('2026000001', 16900)));
    }

    public function test_initiate_sklapa_formu_i_uplatu(): void
    {
        $invoice = $this->faktura(169.00);

        $initiation = $this->gateway->initiate($invoice);

        $this->assertSame('POST', $initiation->method);
        $this->assertSame('https://ipgtest.monri.com/v2/form', $initiation->redirectUrl);
        $this->assertSame($invoice->number, $initiation->gatewayReference);

        $polja = $initiation->fields;

        $this->assertSame('auth-token-123', $polja['authenticity_token']);
        // Iznos ide u feninzima, cijeli broj.
        $this->assertSame('16900', $polja['amount']);
        $this->assertSame($invoice->number, $polja['order_number']);
        $this->assertSame('BAM', $polja['currency']);
        $this->assertSame('bs', $polja['language']);
        $this->assertSame('purchase', $polja['transaction_type']);
        $this->assertSame('1', $polja['tokenize_pan_offered'], 'Bez ponude spremanja kartice nema MIT obnove.');
        $this->assertSame($this->gateway->formDigest($invoice->number, 16900), $polja['digest']);
        $this->assertSame('https://haus.ba/placanje/uspjeh', $polja['success_url_override']);
        $this->assertSame('https://haus.ba/api/v1/webhooks/monri', $polja['callback_url_override']);
        $this->assertArrayNotHasKey('cancel_url_override', $polja, 'Prazna adresa se ne salje.');
        $this->assertArrayNotHasKey('key', $polja, 'Tajna trgovca nikad ne ide klijentu.');

        $payment = Payment::where('invoice_id', $invoice->id)->firstOrFail();

        $this->assertSame(PaymentStatus::Iniciran, $payment->status);
        $this->assertSame(PaymentMethod::Kartica, $payment->method);
        $this->assertSame($invoice->number, $payment->gateway_reference);
        $this->assertEqualsWithDelta(169.0, (float) $payment->amount, 0.001);
    }

    public function test_iznos_sa_feninzima_ne_gubi_ni_fening(): void
    {
        $invoice = $this->faktura(84.35);

        $this->assertSame('8435', $this->gateway->initiate($invoice)->fields['amount']);
    }

    public function test_callback_sa_ispravnim_zaglavljem_prolazi(): void
    {
        $body = '{"order_number":"2026000001","status":"approved"}';
        $timestamp = '1786000000';

        $request = $this->callbackZahtjev($body, 'WP3-callback '.$this->gateway->signCallback($body, $timestamp).' '.$timestamp);

        $this->assertTrue($this->gateway->verifyWebhookSignature($request));
    }

    public function test_callback_sa_pogresnim_potpisom_pada(): void
    {
        $body = '{"order_number":"2026000001","status":"approved"}';

        $request = $this->callbackZahtjev($body, 'WP3-callback '.hash('sha512', 'pogresno').' 1786000000');

        $this->assertFalse($this->gateway->verifyWebhookSignature($request));
    }

    public function test_callback_sa_izmijenjenim_tijelom_pada(): void
    {
        $original = '{"order_number":"2026000001","status":"declined"}';
        $timestamp = '1786000000';
        $potpis = $this->gateway->signCallback($original, $timestamp);

        $podmetnuto = '{"order_number":"2026000001","status":"approved"}';

        $request = $this->callbackZahtjev($podmetnuto, 'WP3-callback '.$potpis.' '.$timestamp);

        $this->assertFalse($this->gateway->verifyWebhookSignature($request));
    }

    public function test_callback_bez_potpisa_pada(): void
    {
        $this->assertFalse($this->gateway->verifyWebhookSignature($this->callbackZahtjev('{}', null)));
    }

    public function test_callback_prolazi_i_kad_je_digest_u_tijelu(): void
    {
        $payload = [
            'order_number' => '2026000001',
            'amount' => 16900,
            'currency' => 'BAM',
            'status' => 'approved',
            'digest' => $this->gateway->formDigest('2026000001', 16900, 'BAM'),
        ];

        $request = Request::create('/api/v1/webhooks/monri', 'POST', $payload);

        $this->assertTrue($this->gateway->verifyWebhookSignature($request));
    }

    public function test_bez_podesenog_kljuca_nijedan_callback_ne_prolazi(): void
    {
        config()->set('services.monri.key', '');

        $body = '{"order_number":"2026000001"}';

        $request = $this->callbackZahtjev($body, 'WP3-callback '.hash('sha512', $body).' 1786000000');

        $this->assertFalse((new MonriGateway)->verifyWebhookSignature($request));
    }

    public function test_normalizacija_callbacka_mapira_monri_polja(): void
    {
        $normalizovano = $this->gateway->normalizeWebhook([
            'order_number' => '2026000001',
            'status' => 'approved',
            'pan_token' => 'monri-token-abc',
            'masked_pan' => '403940xxxxxx1881',
        ]);

        $this->assertSame('2026000001', $normalizovano['reference']);
        $this->assertSame('approved', $normalizovano['status']);
        $this->assertSame('monri-token-abc', $normalizovano['token']);
        $this->assertSame('403940xxxxxx1881', $normalizovano['masked_pan']);

        $odbijeno = $this->gateway->normalizeWebhook(['order_number' => '2026000001', 'status' => 'invalid']);

        $this->assertSame('declined', $odbijeno['status'], 'Sve sto nije approved je za nas odbijenica.');
    }

    public function test_refund_salje_iznos_u_feninzima_i_oznaci_uplatu(): void
    {
        Http::fake([
            '*' => Http::response(['transaction' => ['status' => 'approved', 'order_number' => '2026000001']], 200),
        ]);

        $payment = $this->uspjesnaUplata(169.00);

        $this->assertTrue($this->gateway->refund($payment, 169.00));

        Http::assertSent(function ($request): bool {
            $this->assertSame('https://ipgtest.monri.com/v2/transaction/2026000001/refund', $request->url());
            $this->assertStringStartsWith('WP3-v2 auth-token-123 ', $request->header('Authorization')[0]);

            $body = $request->data();

            $this->assertSame(16900, $body['transaction']['amount']);
            $this->assertSame('BAM', $body['transaction']['currency']);
            $this->assertSame('refund', $body['transaction']['transaction_type']);

            return true;
        });

        $this->assertSame(PaymentStatus::Refundiran, $payment->refresh()->status);
    }

    public function test_djelimican_refund_ostavlja_uplatu_uspjesnom(): void
    {
        Http::fake(['*' => Http::response(['transaction' => ['status' => 'approved']], 200)]);

        $payment = $this->uspjesnaUplata(169.00);

        $this->assertTrue($this->gateway->refund($payment, 50.00));
        $this->assertSame(PaymentStatus::Uspjesan, $payment->refresh()->status);
        $this->assertEqualsWithDelta(50.0, (float) $payment->gateway_payload['refund']['amount'], 0.001);
    }

    public function test_odbijen_refund_ne_mijenja_status_uplate(): void
    {
        Http::fake(['*' => Http::response(['transaction' => ['status' => 'declined']], 200)]);

        $payment = $this->uspjesnaUplata(169.00);

        $this->assertFalse($this->gateway->refund($payment, 169.00));
        $this->assertSame(PaymentStatus::Uspjesan, $payment->refresh()->status);
    }

    public function test_refund_preko_uplacenog_iznosa_se_ne_salje(): void
    {
        Http::fake();

        $payment = $this->uspjesnaUplata(169.00);

        $this->assertFalse($this->gateway->refund($payment, 200.00));

        Http::assertNothingSent();
    }

    public function test_pad_gatewaya_pri_refundu_je_domenska_greska(): void
    {
        Http::fake(['*' => Http::response('bad gateway', 502)]);

        $payment = $this->uspjesnaUplata(169.00);

        $this->expectException(PaymentGatewayException::class);

        $this->gateway->refund($payment, 169.00);
    }

    public function test_charge_token_salje_token_i_mit_oznaku(): void
    {
        Http::fake([
            '*' => Http::response([
                'transaction' => [
                    'status' => 'approved',
                    'order_number' => '2026000007',
                    'pan_token' => 'monri-token-abc',
                    'masked_pan' => '403940xxxxxx1881',
                ],
            ], 200),
        ]);

        $invoice = $this->faktura(390.00, '2026000007');
        $token = $this->token();

        $rezultat = $this->gateway->chargeToken($token, $invoice);

        $this->assertTrue($rezultat->approved);
        $this->assertSame('2026000007', $rezultat->reference);
        $this->assertSame('monri-token-abc', $rezultat->payload['token']);
        $this->assertSame('403940xxxxxx1881', $rezultat->payload['masked_pan']);

        Http::assertSent(function ($request): bool {
            $this->assertSame('https://ipgtest.monri.com/v2/transaction', $request->url());

            $transaction = $request->data()['transaction'];

            $this->assertSame(39000, $transaction['amount']);
            $this->assertSame('monri-token-abc', $transaction['pan_token']);
            $this->assertTrue($transaction['moto'], 'MIT ide bez 3DS koraka.');
            $this->assertSame('2026000007', $transaction['order_number']);

            return true;
        });
    }

    public function test_charge_token_odbijenica_je_uredan_ishod(): void
    {
        Http::fake([
            '*' => Http::response(['transaction' => ['status' => 'declined', 'response_code' => '51']], 200),
        ]);

        $rezultat = $this->gateway->chargeToken($this->token(), $this->faktura(169.00));

        $this->assertFalse($rezultat->approved);
        $this->assertArrayNotHasKey('token', $rezultat->payload);
    }

    public function test_pad_gatewaya_pri_naplati_je_domenska_greska(): void
    {
        Http::fake(['*' => Http::response('', 500)]);

        $this->expectException(PaymentGatewayException::class);

        $this->gateway->chargeToken($this->token(), $this->faktura(169.00));
    }

    private function callbackZahtjev(string $body, ?string $authorization): Request
    {
        $server = ['CONTENT_TYPE' => 'application/json'];

        if ($authorization !== null) {
            $server['HTTP_AUTHORIZATION'] = $authorization;
        }

        return Request::create('/api/v1/webhooks/monri', 'POST', [], [], [], $server, $body);
    }

    private function faktura(float $iznos, string $broj = '2026000001'): Invoice
    {
        return Invoice::create([
            'number' => $broj,
            'user_id' => $this->korisnik()->id,
            'type' => InvoiceType::Pretplata,
            'labor_total' => $iznos,
            'material_total' => 0,
            'total' => $iznos,
            'status' => InvoiceStatus::Nenaplaceno,
        ]);
    }

    private function uspjesnaUplata(float $iznos): Payment
    {
        $invoice = $this->faktura($iznos);

        return Payment::create([
            'invoice_id' => $invoice->id,
            'method' => PaymentMethod::Kartica,
            'amount' => $iznos,
            'status' => PaymentStatus::Uspjesan,
            'gateway_reference' => $invoice->number,
        ]);
    }

    private function token(): PaymentToken
    {
        return PaymentToken::create([
            'user_id' => $this->korisnik()->id,
            'token' => 'monri-token-abc',
            'masked_pan' => '403940xxxxxx1881',
            'active' => true,
        ]);
    }

    private function korisnik(): User
    {
        return User::firstOrCreate(
            ['email' => 'monri@haus.ba'],
            ['name' => 'Test Klijent', 'password' => 'haus12345']
        );
    }
}
