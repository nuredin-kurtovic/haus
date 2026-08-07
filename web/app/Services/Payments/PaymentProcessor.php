<?php

namespace App\Services\Payments;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Mail\RacunMail;
use App\Models\Payment;
use App\Models\PaymentToken;
use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Knjizenje ishoda naplate. Poziva ga webhook, kasnije i MIT obnova.
 *
 * Idempotentno: isti gateway poziv dva puta ne aktivira pretplatu dva puta
 * i ne salje racun dva puta.
 */
class PaymentProcessor
{
    public function __construct(private readonly NotificationService $notifications) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return bool true samo prvi put, kad je uplata zaista proknjizena
     */
    public function approve(Payment $payment, array $payload = []): bool
    {
        $activated = DB::transaction(function () use ($payment, $payload): bool {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Uspjesan) {
                return false;
            }

            $locked->update([
                'status' => PaymentStatus::Uspjesan,
                'gateway_payload' => $payload,
            ]);

            $invoice = $locked->invoice()->first();

            if (! $invoice) {
                return false;
            }

            if ($invoice->status !== InvoiceStatus::Placeno) {
                $invoice->update([
                    'status' => InvoiceStatus::Placeno,
                    'paid_at' => Carbon::now(),
                ]);
            }

            $subscription = $invoice->subscription()->first();

            if ($subscription instanceof Subscription && ! $subscription->isActive()) {
                $starts = Carbon::now();

                $subscription->update([
                    'status' => SubscriptionStatus::Aktivna,
                    'starts_at' => $starts,
                    'ends_at' => $starts->copy()->addYear(),
                    'price_paid' => $invoice->total,
                ]);
            }

            $this->spremiToken($invoice->user_id, $payload);

            $payment->setRawAttributes($locked->getAttributes(), true);

            return true;
        });

        if ($activated) {
            $this->javiKlijentu($payment);
        }

        return $activated;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function decline(Payment $payment, array $payload = []): void
    {
        if ($payment->status === PaymentStatus::Uspjesan) {
            return;
        }

        $payment->update([
            'status' => PaymentStatus::Neuspjesan,
            'gateway_payload' => $payload,
        ]);
    }

    /**
     * Token za MIT obnovu. Stize samo ako je korisnik pristao na cuvanje kartice.
     *
     * @param  array<string, mixed>  $payload
     */
    private function spremiToken(int $userId, array $payload): void
    {
        $token = $payload['token'] ?? null;

        if (! is_string($token) || $token === '') {
            return;
        }

        PaymentToken::query()->where('user_id', $userId)->update(['active' => false]);

        PaymentToken::updateOrCreate(
            ['user_id' => $userId, 'token' => $token],
            [
                'masked_pan' => (string) ($payload['masked_pan'] ?? '****'),
                'active' => true,
            ]
        );
    }

    private function javiKlijentu(Payment $payment): void
    {
        $invoice = $payment->invoice()->with(['user', 'subscription.package'])->first();

        if (! $invoice || ! $invoice->user) {
            return;
        }

        Mail::to($invoice->user->email)->queue(new RacunMail($invoice));

        $subscription = $invoice->subscription;

        if (! $subscription) {
            return;
        }

        $this->notifications->send($invoice->user, 'pretplata_aktivna', [
            'paket' => (string) ($subscription->package->name ?? ''),
            // Predlozak vec ima tacku iza placeholdera, pa je ovdje nema.
            'vrijedi_do' => optional($subscription->ends_at)->format('d.m.Y') ?? '',
        ]);
    }
}
