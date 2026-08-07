<?php

namespace App\Http\Controllers\Api\Dev;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Simulacija kartičnog ishoda za lokalni razvoj.
 *
 * Stranica /placanje/simulacija nema pravi 3DS, pa ovdje sklapamo isti payload
 * koji bi poslao gateway i puštamo ga kroz PaymentProcessor, isti kod put kao
 * pravi webhook. Potpis se ne traži jer je ruta dostupna samo lokalno.
 */
class FakePaymentController extends Controller
{
    public function store(Request $request, PaymentProcessor $processor): JsonResponse
    {
        abort_unless($this->dostupno(), 404);

        $data = $request->validate([
            'reference' => ['required', 'string'],
            'outcome' => ['required', 'in:approved,declined'],
        ]);

        $payment = Payment::query()
            ->where('gateway_reference', $data['reference'])
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Uplata nije pronađena.'], 404);
        }

        $payload = $this->payload($data['reference'], $data['outcome']);

        if ($data['outcome'] === 'approved') {
            $processed = $processor->approve($payment, $payload);
        } else {
            $processor->decline($payment, $payload);
            $processed = true;
        }

        $subscription = $payment->invoice()->first()?->subscription()->first();

        return response()->json([
            'processed' => $processed,
            'subscription_status' => $subscription?->status->value,
        ]);
    }

    /**
     * Ruta postoji samo uz lokalni gateway i upaljen debug. Provjera je u
     * zahtjevu, a ne pri registraciji ruta, da testovi mogu mijenjati podesenja.
     */
    private function dostupno(): bool
    {
        return config('services.haus.payment_gateway') === 'fake'
            && (bool) config('app.debug') === true;
    }

    /**
     * Isti oblik payloada koji salje gateway. Odobrena uplata nosi masku
     * kartice i token, pa se MIT obnova moze vjezbati i lokalno.
     *
     * @return array<string, string>
     */
    private function payload(string $reference, string $outcome): array
    {
        if ($outcome !== 'approved') {
            return ['reference' => $reference, 'status' => 'declined'];
        }

        return [
            'reference' => $reference,
            'status' => 'approved',
            'masked_pan' => '403940xxxxxx1881',
            'token' => 'FAKE-TOKEN-'.Str::uuid()->toString(),
        ];
    }
}
