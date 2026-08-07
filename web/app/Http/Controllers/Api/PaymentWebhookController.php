<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\MonriGateway;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook gatewaya. Nema Bearer token, ulaznica je potpis.
 *
 * Monri i lokalni FakeGateway ne salju ista polja, pa se ulaz prvo svede na
 * zajednicki oblik (reference, status, token, masked_pan), a odatle nadalje
 * je kod isti za oba.
 */
class PaymentWebhookController extends Controller
{
    public function monri(Request $request, PaymentGateway $gateway, PaymentProcessor $processor): JsonResponse
    {
        if (! $gateway->verifyWebhookSignature($request)) {
            Log::warning('Webhook sa neispravnim potpisom.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Potpis nije ispravan.'], 403);
        }

        $data = $gateway instanceof MonriGateway
            ? $this->monriPodaci($request, $gateway)
            : $this->fakePodaci($request);

        $payment = Payment::query()
            ->where('gateway_reference', $data['reference'])
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Uplata nije pronađena.'], 404);
        }

        if ($data['status'] === 'approved') {
            $processed = $processor->approve($payment, $data);

            return response()->json([
                'status' => 'uspjesan',
                // false znaci da je isti webhook vec bio proknjizen.
                'processed' => $processed,
            ]);
        }

        $processor->decline($payment, $data);

        return response()->json(['status' => 'neuspjesan', 'processed' => true]);
    }

    /**
     * Monri callback: order_number je nasa referenca, pan_token je token za MIT.
     * Status koji nije uspjesan (declined, invalid, error) je za nas odbijenica.
     *
     * @return array<string, mixed>
     */
    private function monriPodaci(Request $request, MonriGateway $gateway): array
    {
        $request->validate([
            'order_number' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', 'max:64'],
            'masked_pan' => ['nullable', 'string', 'max:255'],
            'pan_token' => ['nullable', 'string', 'max:255'],
            'amount' => ['nullable', 'integer'],
            'currency' => ['nullable', 'string', 'max:8'],
        ]);

        return $gateway->normalizeWebhook($request->all());
    }

    /**
     * Lokalni oblik, isti kakav salje stranica simulacije.
     *
     * @return array<string, mixed>
     */
    private function fakePodaci(Request $request): array
    {
        $data = $request->validate([
            'reference' => ['required', 'string'],
            'status' => ['required', 'in:approved,declined'],
            'masked_pan' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
        ]);

        return $data;
    }
}
