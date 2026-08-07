<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PaymentGatewayException;
use App\Mail\RacunMail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentToken;
use App\Models\Subscription;
use App\Services\NotificationService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * Knjizenje ishoda naplate. Poziva ga webhook, kasnije i MIT obnova.
 *
 * Idempotentno: isti gateway poziv dva puta ne aktivira pretplatu dva puta
 * i ne salje racun dva puta.
 */
class PaymentProcessor
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly PaymentGateway $gateway,
    ) {}

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

            if ($subscription instanceof Subscription) {
                $this->knjiziPeriod($subscription, $invoice);
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
     * Pun ili djelimican povrat po fakturi.
     *
     * Iznos null znaci povrat cijelog neponistenog dijela. Poziv prema
     * gatewayu ide kroz interfejs, pa isti kod radi i sa pravim Monrijem.
     */
    public function refund(Invoice $invoice, ?float $amount = null): Invoice
    {
        /** @var Payment|null $payment */
        $payment = $invoice->payments()
            ->where('status', PaymentStatus::Uspjesan)
            ->latest('id')
            ->first();

        if (! $payment) {
            throw ValidationException::withMessages([
                'invoice' => 'Faktura nema proknjiženu uplatu, povrat nije moguć.',
            ]);
        }

        $placeno = (float) $payment->amount;
        $vecVraceno = (float) $invoice->refunded_amount;
        $preostalo = round($placeno - $vecVraceno, 2);

        $iznos = round($amount ?? $preostalo, 2);

        if ($iznos <= 0) {
            throw ValidationException::withMessages([
                'amount' => 'Iznos povrata mora biti veći od nule.',
            ]);
        }

        if ($iznos > $preostalo) {
            throw ValidationException::withMessages([
                'amount' => 'Iznos povrata je veći od uplaćenog iznosa. Najviše možete vratiti '
                    .number_format($preostalo, 2, ',', '.').' KM.',
            ]);
        }

        try {
            $prosao = $this->gateway->refund($payment, $iznos);
        } catch (PaymentGatewayException $e) {
            Log::error('Povrat nije mogao doci do gatewaya.', [
                'invoice' => $invoice->number,
                'poruka' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'invoice' => 'Gateway trenutno nije dostupan. Pokušajte ponovo za nekoliko minuta.',
            ]);
        }

        if (! $prosao) {
            throw ValidationException::withMessages([
                'invoice' => 'Gateway je odbio povrat.',
            ]);
        }

        $ukupnoVraceno = round($vecVraceno + $iznos, 2);

        $invoice->update([
            'refunded_amount' => $ukupnoVraceno,
            'status' => $ukupnoVraceno >= $placeno
                ? InvoiceStatus::Refundirano
                : InvoiceStatus::DjelimicnoRefundirano,
        ]);

        return $invoice->refresh();
    }

    /**
     * Postavi ili produzi period pretplate po uplacenoj fakturi.
     *
     * Prva uplata pocinje danas. Uplata obnove nastavlja na stari kraj, pa
     * klijent ne gubi dane dok uplata putuje, i resetuje prava po adresama.
     * Isti kod vrijedi i za MIT naplatu i za uplatnicu placenu poslije isteka.
     */
    private function knjiziPeriod(Subscription $subscription, Invoice $invoice): void
    {
        // Rok pretplate mijenja samo faktura pretplate. Faktura za rad ne dira rok.
        if ($invoice->type !== InvoiceType::Pretplata) {
            return;
        }

        // Aktivna pretplata kojoj rok jos traje nema sta da produzava.
        if ($subscription->isActive() && $subscription->ends_at?->isFuture()) {
            return;
        }

        $obnova = $subscription->ends_at !== null;
        $starts = $obnova ? $this->pocetakObnove($subscription) : Carbon::now();

        $subscription->update([
            'status' => SubscriptionStatus::Aktivna,
            'starts_at' => $starts,
            'ends_at' => $starts->copy()->addYear(),
            'price_paid' => $invoice->total,
            // Novi period, novi podsjetnik 60 dana prije sljedeceg isteka.
            'renewal_reminder_sent_at' => null,
        ]);

        if ($obnova) {
            $this->resetujPrava($subscription);
        }
    }

    /**
     * Obnova nastavlja tamo gdje je stari period stao. Ako je uplata kasnila
     * vise od godinu dana, stari kraj vise nema smisla i period pocinje danas.
     */
    private function pocetakObnove(Subscription $subscription): Carbon
    {
        $stariKraj = Carbon::instance($subscription->ends_at);

        return $stariKraj->copy()->addYear()->isPast() ? Carbon::now() : $stariKraj;
    }

    /**
     * Novi period vraca brojace izlazaka i pregleda na ono sto paket daje.
     * Krediti (besplatne intervencije) su obecanje koje smo vec dali, ostaju.
     */
    private function resetujPrava(Subscription $subscription): void
    {
        $package = $subscription->package()->first();

        if (! $package) {
            return;
        }

        $subscription->properties()->update([
            'remaining_visits' => (int) $package->visits_per_year,
            'remaining_inspections' => (int) $package->inspections_per_year,
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
