<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\InvoiceStatus;
use App\Enums\InvoiceType;
use App\Enums\PaymentMethod;
use App\Enums\PropertyUse;
use App\Enums\SubscriptionStatus;
use App\Mail\PonudaZahtjevMail;
use App\Mail\UplatnicaMail;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Registracija klijenta: korisnik, pretplata, adrese, faktura i pokretanje naplate.
 *
 * Pretplata se ne aktivira ovdje. Aktivira je uplata, kroz PaymentProcessor.
 */
class RegistrationService
{
    /**
     * HAUS Pro od ovoliko stanova nema automatsku naplatu, ide na ponudu.
     */
    public const PONUDA_OD_STANOVA = 10;

    public function __construct(
        private readonly PriceCalculator $prices,
        private readonly SettingsService $settings,
        private readonly PaymentGateway $gateway,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $properties
     */
    public function register(
        Package $package,
        string $name,
        string $email,
        string $password,
        PaymentMethod $method,
        array $properties,
    ): RegistrationResult {
        $count = count($properties);
        $total = $this->prices->subscriptionTotal($package, $count, $this->tiers());
        $isPonuda = $package->is_per_apartment && $count >= self::PONUDA_OD_STANOVA;

        $result = DB::transaction(function () use ($package, $name, $email, $password, $method, $properties, $total, $isPonuda): RegistrationResult {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'notif_push' => true,
                'notif_email' => true,
                'notif_marketing' => false,
            ]);

            $user->assignRole('klijent');

            $subscription = Subscription::create([
                'user_id' => $user->id,
                'package_id' => $package->id,
                'status' => $isPonuda ? SubscriptionStatus::Ponuda : SubscriptionStatus::CekanjeUplate,
                // Datumi se postavljaju tek pri aktivaciji, kad uplata legne.
                'starts_at' => null,
                'ends_at' => null,
                'auto_renew' => true,
                'price_paid' => null,
                'free_interventions' => 0,
            ]);

            foreach ($properties as $property) {
                SubscriptionProperty::create([
                    'subscription_id' => $subscription->id,
                    'city_id' => (int) $property['city_id'],
                    'street' => (string) $property['street'],
                    'use' => PropertyUse::tryFrom((string) ($property['use'] ?? '')) ?? PropertyUse::Zivim,
                    'contact_name' => $property['contact_name'] ?? null,
                    'contact_note' => $property['contact_note'] ?? null,
                    'remaining_visits' => (int) $package->visits_per_year,
                    'remaining_inspections' => (int) $package->inspections_per_year,
                ]);
            }

            $subscription->setRelation('package', $package);
            $subscription->load('properties.city');

            if ($isPonuda) {
                return new RegistrationResult($user, $subscription, $total);
            }

            $invoice = Invoice::create([
                'number' => Invoice::nextNumber(),
                'user_id' => $user->id,
                'subscription_id' => $subscription->id,
                'type' => InvoiceType::Pretplata,
                'labor_total' => $total,
                'material_total' => 0,
                'total' => $total,
                'status' => InvoiceStatus::Nenaplaceno,
            ]);

            $initiation = $method === PaymentMethod::Kartica
                ? $this->gateway->initiate($invoice)
                : null;

            return new RegistrationResult($user, $subscription, $total, $invoice, $initiation);
        });

        $this->posaljiMejlove($result, $method);

        return $result;
    }

    /**
     * Mejlovi idu tek kad transakcija prodje.
     */
    private function posaljiMejlove(RegistrationResult $result, PaymentMethod $method): void
    {
        if ($result->isPonuda()) {
            Mail::to($this->dispatcherEmail())->queue(new PonudaZahtjevMail($result->subscription));

            return;
        }

        if ($method === PaymentMethod::Uplatnica && $result->invoice) {
            Mail::to($result->user->email)->queue(new UplatnicaMail($result->invoice));

            $result->invoice->forceFill(['sent_at' => Carbon::now()])->save();
        }
    }

    /**
     * @return array<int, array{min: int, max: int|null, pct: int}>
     */
    private function tiers(): array
    {
        $tiers = $this->settings->get('pro_volume_tiers', []);

        return is_array($tiers) ? $tiers : [];
    }

    private function dispatcherEmail(): string
    {
        $fromSettings = $this->settings->get('dispecer_email');

        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }

        return (string) config('services.haus.dispatcher_email');
    }
}
