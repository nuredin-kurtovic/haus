<?php

namespace App\Http\Controllers\Api;

use App\Contracts\PaymentGateway;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook gatewaya. Nema Bearer token, ulaznica je potpis.
 */
class PaymentWebhookController extends Controller
{
    public function monri(Request $request, PaymentGateway $gateway, PaymentProcessor $processor): JsonResponse
    {
        if (! $gateway->verifyWebhookSignature($request)) {
            Log::warning('Webhook sa neispravnim potpisom.', ['ip' => $request->ip()]);

            return response()->json(['message' => 'Potpis nije ispravan.'], 403);
        }

        $data = $request->validate([
            'reference' => ['required', 'string'],
            'status' => ['required', 'in:approved,declined'],
            'masked_pan' => ['nullable', 'string', 'max:255'],
            'token' => ['nullable', 'string', 'max:255'],
        ]);

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
}
