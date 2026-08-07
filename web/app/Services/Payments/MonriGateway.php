<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentInitiation;
use App\Contracts\PaymentResult;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\PaymentGatewayException;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentToken;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Monri Payments, BiH procesor.
 *
 * Ovaj razred je jedini dodir sa Monrijem. Sve pretpostavke o oblicima
 * zahtjeva su ili u config/services.php (monri blok) ili su ovdje oznacene
 * sa TODO MONRI, da se pri onboardingu potvrde iz jedne tacke.
 *
 * Sto je poznato javno i sto je ovdje implementirano:
 * - WebPay hosted stranica prima POST forme sa skrivenim poljima, iznos je u
 *   feningima (cijeli broj), a potpis je digest = sha512(key + order_number
 *   + amount + currency).
 * - API transakcije se autorizuju zaglavljem WP3-v2 sa authenticity_token,
 *   vremenskom oznakom i digestom nad tijelom zahtjeva.
 *
 * TODO MONRI (potvrditi iz onboarding dokumentacije prije produkcije):
 * 1. Tacne putanje: form_path, transaction_path, refund_path.
 * 2. Naziv i oblik zaglavlja kojim Monri potpisuje callback (ovdje: authorization
 *    WP3-callback digest timestamp), i redoslijed polja u digestu callbacka.
 * 3. Naziv polja za token kartice u odgovoru (ovdje: pan_token) i u zahtjevu
 *    naplate po tokenu (ovdje: pan_token uz moto = true).
 * 4. Koje polje nosi MIT oznaku za obnovu bez 3DS koraka.
 * 5. Da li refund ide na zasebnu putanju ili kao transaction_type refund.
 */
class MonriGateway implements PaymentGateway
{
    /** Sve sto ide prema Monriju racunamo u feninzima, cijeli broj. */
    private const U_FENINGE = 100;

    public function initiate(Invoice $invoice): PaymentInitiation
    {
        // Referenca je broj fakture. Monri je zna kao order_number i vraca je
        // u callbacku, pa je to jedina veza koja nam treba.
        $orderNumber = (string) $invoice->number;
        $amount = $this->uFeninge((float) $invoice->total);
        $currency = $this->currency();

        Payment::create([
            'invoice_id' => $invoice->id,
            'method' => PaymentMethod::Kartica,
            'amount' => $invoice->total,
            'status' => PaymentStatus::Iniciran,
            'gateway_reference' => $orderNumber,
        ]);

        $fields = [
            'authenticity_token' => $this->authenticityToken(),
            'amount' => (string) $amount,
            'order_number' => $orderNumber,
            'currency' => $currency,
            'transaction_type' => (string) config('services.monri.transaction_type', 'purchase'),
            'order_info' => $this->orderInfo($invoice),
            'language' => (string) config('services.monri.language', 'bs'),
            'digest' => $this->formDigest($orderNumber, $amount, $currency),
            // Ponudi klijentu spremanje kartice. Bez toga nema MIT obnove.
            'tokenize_pan_offered' => '1',
            'number_of_installments' => '0',
        ];

        foreach ($this->urlOverrides() as $polje => $url) {
            $fields[$polje] = $url;
        }

        return new PaymentInitiation(
            redirectUrl: $this->webpayUrl(),
            gatewayReference: $orderNumber,
            method: 'POST',
            fields: $fields,
        );
    }

    /**
     * Potpis WebPay digesta: sha512(key + order_number + amount + currency).
     * Izdvojeno da ga testovi mogu provjeriti bez HTTP sloja.
     */
    public function formDigest(string $orderNumber, int $amount, ?string $currency = null): string
    {
        return hash('sha512', $this->key().$orderNumber.$amount.($currency ?? $this->currency()));
    }

    /**
     * Callback bez potpisa se odbija, uvijek.
     *
     * Priznajemo dva oblika, jer Monri okruzenja nisu ista:
     * 1. zaglavlje authorization: WP3-callback {digest} {timestamp}, gdje je
     *    digest = sha512(key + timestamp + sirovo tijelo zahtjeva);
     * 2. polje digest u tijelu, sha512(key + order_number + amount + currency).
     * TODO MONRI: kad se potvrdi koji oblik stize, drugi obrisati.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        if ($this->key() === '') {
            Log::warning('Monri kljuc nije podesen, callback se ne moze provjeriti.');

            return false;
        }

        return $this->potpisIzZaglavlja($request) || $this->potpisIzTijela($request);
    }

    /**
     * Pun ili djelimican povrat prema Monri API-ju.
     */
    public function refund(Payment $payment, ?float $amount = null): bool
    {
        if ($payment->status !== PaymentStatus::Uspjesan) {
            return false;
        }

        $iznos = $amount ?? (float) $payment->amount;

        if ($iznos <= 0 || $iznos > (float) $payment->amount) {
            return false;
        }

        $orderNumber = (string) $payment->gateway_reference;
        $minor = $this->uFeninge($iznos);

        $body = [
            'transaction' => [
                'transaction_type' => (string) config('services.monri.refund_transaction_type', 'refund'),
                'amount' => $minor,
                'currency' => $this->currency(),
                'order_number' => $orderNumber,
            ],
        ];

        $odgovor = $this->posalji('refund', $this->refundUrl($orderNumber), $body);

        $status = $this->statusIzOdgovora($odgovor);
        $prosao = in_array($status, $this->uspjesniStatusi(), true);

        $payload = is_array($payment->gateway_payload) ? $payment->gateway_payload : [];
        $payload['refund'] = [
            'amount' => round($iznos, 2),
            'at' => Carbon::now()->toIso8601String(),
            'response' => $odgovor,
        ];

        if (! $prosao) {
            $payment->update(['gateway_payload' => $payload]);

            return false;
        }

        $payment->update([
            'status' => $iznos >= (float) $payment->amount ? PaymentStatus::Refundiran : PaymentStatus::Uspjesan,
            'gateway_payload' => $payload,
        ]);

        return true;
    }

    /**
     * Naplata po spremljenom tokenu, bez prisustva klijenta.
     */
    public function chargeToken(PaymentToken $token, Invoice $invoice): PaymentResult
    {
        $orderNumber = (string) $invoice->number;
        $amount = $this->uFeninge((float) $invoice->total);
        $currency = $this->currency();

        $body = [
            'transaction' => [
                'transaction_type' => (string) config('services.monri.transaction_type', 'purchase'),
                'amount' => $amount,
                'currency' => $currency,
                'order_number' => $orderNumber,
                'order_info' => $this->orderInfo($invoice),
                'language' => (string) config('services.monri.language', 'bs'),
                'digest' => $this->formDigest($orderNumber, $amount, $currency),
                // TODO MONRI: potvrditi naziv polja za token i oznaku MIT-a.
                'pan_token' => (string) $token->token,
                // moto = merchant initiated, bez 3DS koraka i bez klijenta.
                'moto' => true,
                'initiator' => 'merchant',
            ],
        ];

        $odgovor = $this->posalji('charge_token', $this->transactionUrl(), $body);

        $status = $this->statusIzOdgovora($odgovor);
        $referenca = $this->referencaIzOdgovora($odgovor) ?: $orderNumber;

        if (! in_array($status, $this->uspjesniStatusi(), true)) {
            return PaymentResult::declined($referenca, $odgovor);
        }

        return PaymentResult::approved($referenca, $this->normalizujPayload($odgovor, $referenca));
    }

    /**
     * Sirovi callback prevodimo u polja koja PaymentProcessor razumije.
     *
     * @param  array<string, mixed>  $payload
     * @return array{reference: string, status: string, token: string|null, masked_pan: string|null, gateway: string, raw: array<string, mixed>}
     */
    public function normalizeWebhook(array $payload): array
    {
        $status = strtolower((string) ($payload['status'] ?? ''));

        return [
            'reference' => (string) ($payload['order_number'] ?? ''),
            'status' => in_array($status, $this->uspjesniStatusi(), true) ? 'approved' : 'declined',
            'token' => $this->token($payload),
            'masked_pan' => isset($payload['masked_pan']) ? (string) $payload['masked_pan'] : null,
            'gateway' => 'monri',
            'raw' => $payload,
        ];
    }

    /**
     * Jedan HTTP poziv prema Monriju, sa potpisom i mapiranjem gresaka.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    private function posalji(string $operacija, string $url, array $body): array
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        try {
            /** @var Response $odgovor */
            $odgovor = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'Authorization' => $this->apiAuthorization($json),
            ])
                ->timeout($this->timeout())
                ->connectTimeout($this->connectTimeout())
                ->withBody($json, 'application/json')
                ->post($url)
                ->throw();
        } catch (RequestException|ConnectionException $e) {
            Log::error('Monri poziv nije uspio.', [
                'operacija' => $operacija,
                'url' => $url,
                'poruka' => $e->getMessage(),
            ]);

            throw PaymentGatewayException::transport($operacija, $e);
        }

        $podaci = $odgovor->json();

        if (! is_array($podaci)) {
            throw PaymentGatewayException::odgovor($operacija, $odgovor->status(), (string) $odgovor->body());
        }

        return $podaci;
    }

    /**
     * Zaglavlje WP3-v2: token, vremenska oznaka i digest nad tijelom.
     * TODO MONRI: potvrditi redoslijed dijelova digesta.
     */
    private function apiAuthorization(string $json): string
    {
        $timestamp = (string) Carbon::now()->timestamp;
        $token = $this->authenticityToken();
        $digest = hash('sha512', $this->key().$timestamp.$token.$json);

        return 'WP3-v2 '.$token.' '.$timestamp.' '.$digest;
    }

    private function potpisIzZaglavlja(Request $request): bool
    {
        $header = trim((string) $request->header(
            (string) config('services.monri.callback_signature_header', 'authorization')
        ));

        if ($header === '') {
            return false;
        }

        $dijelovi = preg_split('/\s+/', $header) ?: [];

        // Ocekujemo: WP3-callback {digest} {timestamp}
        if (count($dijelovi) < 3) {
            return false;
        }

        [$shema, $digest, $timestamp] = [$dijelovi[0], $dijelovi[1], $dijelovi[2]];

        if (strcasecmp($shema, (string) config('services.monri.callback_signature_scheme', 'WP3-callback')) !== 0) {
            return false;
        }

        $ocekivano = hash('sha512', $this->key().$timestamp.$request->getContent());

        return hash_equals($ocekivano, strtolower($digest));
    }

    private function potpisIzTijela(Request $request): bool
    {
        $digest = (string) $request->input('digest', '');
        $orderNumber = (string) $request->input('order_number', '');
        $amount = $request->input('amount');

        if ($digest === '' || $orderNumber === '' || $amount === null) {
            return false;
        }

        $ocekivano = hash(
            'sha512',
            $this->key().$orderNumber.(int) $amount.(string) $request->input('currency', $this->currency())
        );

        return hash_equals($ocekivano, strtolower($digest));
    }

    /**
     * Potpis kakav bi Monri poslao u zaglavlju. Koriste ga testovi.
     */
    public function signCallback(string $body, string $timestamp): string
    {
        return hash('sha512', $this->key().$timestamp.$body);
    }

    /**
     * @param  array<string, mixed>  $odgovor
     */
    private function statusIzOdgovora(array $odgovor): string
    {
        $transaction = is_array($odgovor['transaction'] ?? null) ? $odgovor['transaction'] : $odgovor;

        return strtolower((string) ($transaction['status'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $odgovor
     */
    private function referencaIzOdgovora(array $odgovor): string
    {
        $transaction = is_array($odgovor['transaction'] ?? null) ? $odgovor['transaction'] : $odgovor;

        return (string) ($transaction['order_number'] ?? '');
    }

    /**
     * Token i maska iz odgovora idu u oblik koji PaymentProcessor sprema.
     *
     * @param  array<string, mixed>  $odgovor
     * @return array<string, mixed>
     */
    private function normalizujPayload(array $odgovor, string $referenca): array
    {
        $transaction = is_array($odgovor['transaction'] ?? null) ? $odgovor['transaction'] : $odgovor;

        $payload = [
            'reference' => $referenca,
            'status' => 'approved',
            'gateway' => 'monri',
            'raw' => $odgovor,
        ];

        $token = $this->token($transaction);

        if ($token !== null) {
            $payload['token'] = $token;
            $payload['masked_pan'] = (string) ($transaction['masked_pan'] ?? '****');
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $polja
     */
    private function token(array $polja): ?string
    {
        foreach (['pan_token', 'token'] as $kljuc) {
            $vrijednost = $polja[$kljuc] ?? null;

            if (is_string($vrijednost) && $vrijednost !== '') {
                return $vrijednost;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function uspjesniStatusi(): array
    {
        $statusi = config('services.monri.approved_statuses', ['approved']);

        return is_array($statusi) ? array_map('strtolower', $statusi) : ['approved'];
    }

    /**
     * Iznos ide u feninzima, cijeli broj. Zaokruzujemo prije mnozenja da
     * decimalni zapis iz baze ne odsijece fening.
     */
    private function uFeninge(float $iznos): int
    {
        return (int) round($iznos * self::U_FENINGE);
    }

    private function orderInfo(Invoice $invoice): string
    {
        $opis = $invoice->type->value === 'pretplata'
            ? 'HAUS godišnja pretplata'
            : 'HAUS intervencija';

        return $opis.', račun '.$invoice->number;
    }

    /**
     * @return array<string, string>
     */
    private function urlOverrides(): array
    {
        $polja = [
            'success_url_override' => (string) config('services.monri.success_url', ''),
            'cancel_url_override' => (string) config('services.monri.cancel_url', ''),
            'callback_url_override' => (string) config('services.monri.callback_url', ''),
        ];

        return array_filter($polja, fn (string $url): bool => $url !== '');
    }

    private function webpayUrl(): string
    {
        return $this->spoji(
            (string) config('services.monri.webpay_base'),
            (string) config('services.monri.form_path', '/v2/form')
        );
    }

    private function transactionUrl(): string
    {
        return $this->spoji(
            (string) config('services.monri.api_base'),
            (string) config('services.monri.transaction_path', '/v2/transaction')
        );
    }

    private function refundUrl(string $orderNumber): string
    {
        $putanja = str_replace(
            '{order_number}',
            rawurlencode($orderNumber),
            (string) config('services.monri.refund_path', '/v2/transaction/{order_number}/refund')
        );

        return $this->spoji((string) config('services.monri.api_base'), $putanja);
    }

    private function spoji(string $base, string $path): string
    {
        return rtrim($base, '/').'/'.ltrim($path, '/');
    }

    private function key(): string
    {
        return (string) config('services.monri.key', '');
    }

    private function authenticityToken(): string
    {
        return (string) config('services.monri.authenticity_token', '');
    }

    private function currency(): string
    {
        return (string) config('services.monri.currency', 'BAM');
    }

    private function timeout(): int
    {
        return (int) config('services.monri.timeout', 20);
    }

    private function connectTimeout(): int
    {
        return (int) config('services.monri.connect_timeout', 5);
    }
}
