<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentInitiation;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Lokalni gateway bez Monri kredencijala.
 *
 * initiate() vodi na stranicu simulacije gdje frontend nudi dugmad Uspjeh i
 * Neuspjeh. Ta dugmad gadjaju isti webhook koji ce gadjati i pravi Monri.
 */
class FakeGateway implements PaymentGateway
{
    public function initiate(Invoice $invoice): PaymentInitiation
    {
        $reference = 'FAKE-'.Str::uuid()->toString();

        Payment::create([
            'invoice_id' => $invoice->id,
            'method' => PaymentMethod::Kartica,
            'amount' => $invoice->total,
            'status' => PaymentStatus::Iniciran,
            'gateway_reference' => $reference,
        ]);

        $base = rtrim((string) config('app.url'), '/');

        return new PaymentInitiation(
            redirectUrl: $base.'/placanje/simulacija?ref='.$reference,
            gatewayReference: $reference,
        );
    }

    /**
     * Potpis je sha256 nad sirovim tijelom zahtjeva i tajnom iz konfiguracije.
     */
    public function verifyWebhookSignature(Request $request): bool
    {
        $signature = (string) $request->header('X-Fake-Signature');

        if ($signature === '') {
            return false;
        }

        $expected = hash('sha256', $request->getContent().$this->secret());

        return hash_equals($expected, $signature);
    }

    /**
     * Povrat. Lokalno nema poziva prema banci, pa uplatu samo oznacimo.
     * Pun povrat gasi uplatu, djelimicni je ostavlja uspjesnom.
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

        $payload = is_array($payment->gateway_payload) ? $payment->gateway_payload : [];
        $payload['refund'] = ['amount' => round($iznos, 2), 'at' => now()->toIso8601String()];

        $payment->update([
            'status' => $iznos >= (float) $payment->amount ? PaymentStatus::Refundiran : PaymentStatus::Uspjesan,
            'gateway_payload' => $payload,
        ]);

        return true;
    }

    /**
     * Potpis kakav bi gateway poslao. Koriste ga testovi i stranica simulacije.
     */
    public function signPayload(string $payload): string
    {
        return hash('sha256', $payload.$this->secret());
    }

    private function secret(): string
    {
        return (string) config('services.haus.fake_gateway_secret');
    }
}
