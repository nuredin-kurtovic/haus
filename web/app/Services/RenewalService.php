<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Contracts\PaymentResult;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Exceptions\PaymentGatewayException;
use App\Mail\UplatnicaMail;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentToken;
use App\Models\Subscription;
use App\Services\Payments\PaymentProcessor;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Godisnja obnova pretplate.
 *
 * Tok na dan isteka:
 * 1. auto_renew ugasen: pretplata prelazi u isteklu, nista se ne naplacuje.
 * 2. auto_renew upaljen, ima aktivan token: faktura za novi period, pa MIT
 *    naplata po tokenu. Odobreno vodi kroz PaymentProcessor, isti kod kao
 *    webhook, pa se pretplata produzi, prava resetuju i racun ode na mejl.
 *    Odbijeno: pretplata istice, klijent dobija uplatnicu kao rezervni put.
 * 3. auto_renew upaljen, nema tokena: faktura i uplatnica, pretplata istice.
 *
 * Novo stanje pretplate se ne uvodi. Pretplata koja ceka uplatu obnove je
 * istekla, a faktura obnove je nenaplacena. Uplata je vraca u aktivnu kroz
 * PaymentProcessor, koji zna razliku izmedju prve uplate i obnove.
 *
 * Cijena obnove se racuna po TRENUTNOM stanju paketa i broju adresa, nikad
 * se ne prepisuje stari price_paid.
 */
class RenewalService
{
    public const OBNOVLJENA = 'obnovljena';

    public const ODBIJENA = 'odbijena';

    public const BEZ_TOKENA = 'bez_tokena';

    public const ISTEKLA = 'istekla';

    public const PRESKOCENA = 'preskocena';

    public function __construct(
        private readonly PriceCalculator $prices,
        private readonly SettingsService $settings,
        private readonly PaymentGateway $gateway,
        private readonly PaymentProcessor $processor,
    ) {}

    /**
     * Pretplate kojima je rok istekao, a jos vode kao aktivne.
     *
     * @return Collection<int, Subscription>
     */
    public function dospjele(?Carbon $na = null): Collection
    {
        $granica = ($na ?? Carbon::now())->copy()->endOfDay();

        return Subscription::query()
            ->where('status', SubscriptionStatus::Aktivna)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $granica)
            ->with(['user', 'package', 'properties'])
            ->orderBy('id')
            ->get();
    }

    /**
     * Obradi jednu pretplatu. Vraca kljuc ishoda, za ispis i za testove.
     */
    public function obradi(Subscription $subscription): string
    {
        if (! $subscription->isActive()) {
            return self::PRESKOCENA;
        }

        if (! $subscription->auto_renew) {
            $this->oznaciIsteklom($subscription);

            return self::ISTEKLA;
        }

        $invoice = $this->fakturaObnove($subscription);
        $token = $this->token($subscription);

        if (! $token) {
            $this->oznaciIsteklom($subscription);
            $this->posaljiUplatnicu($invoice);

            return self::BEZ_TOKENA;
        }

        $rezultat = $this->naplati($token, $invoice);

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'method' => PaymentMethod::Kartica,
            'amount' => $invoice->total,
            'status' => PaymentStatus::Iniciran,
            'gateway_reference' => $rezultat->reference,
        ]);

        if ($rezultat->approved) {
            // Odavde je tok isti kao kod webhooka: produzenje, reset prava,
            // racun na mejl i obavjestenje pretplata_aktivna.
            $this->processor->approve($payment, $rezultat->payload);

            return self::OBNOVLJENA;
        }

        $this->processor->decline($payment, $rezultat->payload);
        $this->oznaciIsteklom($subscription);
        $this->posaljiUplatnicu($invoice);

        return self::ODBIJENA;
    }

    /**
     * Iznos obnove po trenutnom paketu i broju adresa.
     */
    public function iznos(Subscription $subscription): int
    {
        $package = $subscription->package;

        if (! $package) {
            return (int) round((float) $subscription->price_paid);
        }

        $adresa = max(1, $subscription->properties()->count());

        return $this->prices->subscriptionTotal($package, $adresa, $this->tiers());
    }

    /**
     * Faktura za novi period. Idempotentno: ako vec postoji nenaplacena
     * faktura pretplate, ista se koristi i drugi put, bez dupliranja.
     */
    private function fakturaObnove(Subscription $subscription): Invoice
    {
        $postojeca = $subscription->invoices()
            ->where('type', InvoiceType::Pretplata)
            ->where('status', InvoiceStatus::Nenaplaceno)
            ->latest('id')
            ->first();

        if ($postojeca instanceof Invoice) {
            return $postojeca;
        }

        $iznos = $this->iznos($subscription);

        return DB::transaction(fn (): Invoice => Invoice::create([
            'number' => Invoice::nextNumber(),
            'user_id' => $subscription->user_id,
            'subscription_id' => $subscription->id,
            'type' => InvoiceType::Pretplata,
            'labor_total' => $iznos,
            'material_total' => 0,
            'total' => $iznos,
            'status' => InvoiceStatus::Nenaplaceno,
        ]));
    }

    /**
     * Kvar u komunikaciji sa gatewayem tretiramo kao odbijenicu: klijent dobija
     * uplatnicu i ne ostaje bez puta da plati. Greska ide u log.
     */
    private function naplati(PaymentToken $token, Invoice $invoice): PaymentResult
    {
        try {
            return $this->gateway->chargeToken($token, $invoice);
        } catch (PaymentGatewayException $e) {
            Log::error('MIT naplata obnove nije dosla do gatewaya.', [
                'invoice' => $invoice->number,
                'poruka' => $e->getMessage(),
            ]);

            return PaymentResult::declined($invoice->number, [
                'status' => 'declined',
                'reason' => 'gateway_nedostupan',
            ]);
        }
    }

    private function token(Subscription $subscription): ?PaymentToken
    {
        return PaymentToken::query()
            ->where('user_id', $subscription->user_id)
            ->where('active', true)
            ->where(fn ($query) => $query->whereNull('expires_at')->orWhere('expires_at', '>', Carbon::now()))
            ->latest('id')
            ->first();
    }

    private function oznaciIsteklom(Subscription $subscription): void
    {
        if ($subscription->status === SubscriptionStatus::Istekla) {
            return;
        }

        $subscription->update(['status' => SubscriptionStatus::Istekla]);
    }

    /**
     * Rezervni put kad kartica ne prodje: uplatnica na mejl. Salje se jednom
     * po fakturi, sto cuva sent_at.
     */
    private function posaljiUplatnicu(Invoice $invoice): void
    {
        if ($invoice->sent_at !== null) {
            return;
        }

        $user = $invoice->user()->first();

        if (! $user) {
            return;
        }

        Mail::to($user->email)->queue(new UplatnicaMail($invoice));

        $invoice->forceFill(['sent_at' => Carbon::now()])->save();
    }

    /**
     * @return array<int, array{min: int, max: int|null, pct: int}>
     */
    private function tiers(): array
    {
        $tiers = $this->settings->get('pro_volume_tiers', []);

        return is_array($tiers) ? $tiers : [];
    }
}
