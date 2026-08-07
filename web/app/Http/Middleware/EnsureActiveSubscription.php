<?php

namespace App\Http\Middleware;

use App\Enums\SubscriptionStatus;
use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prijava kvara traži pretplatu u stanju aktivna.
 *
 * Guard je namjerno samo na prijavi kvara, ne na cijeloj klijentskoj grupi:
 * pregled pretplate i profila mora raditi i dok uplata nije legla.
 */
class EnsureActiveSubscription
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->activeSubscription()->exists()) {
            return $next($request);
        }

        $subscription = $user?->subscriptions()->latest('id')->first();

        return response()->json([
            'message' => $this->poruka($subscription),
            'subscription_status' => $subscription?->status->value,
        ], 403);
    }

    private function poruka(?Subscription $subscription): string
    {
        if (! $subscription) {
            return 'Nemate pretplatu. Odaberite paket da biste prijavili kvar.';
        }

        return match ($subscription->status) {
            SubscriptionStatus::CekanjeUplate => 'Vaša pretplata još nije aktivna. Prijava kvara je moguća čim uplata legne.',
            SubscriptionStatus::Ponuda => 'Vaš zahtjev za ponudu je u obradi. Prijava kvara je moguća čim pretplata krene.',
            SubscriptionStatus::Istekla => 'Vaša pretplata je istekla. Obnovite je da biste prijavili kvar.',
            SubscriptionStatus::Otkazana => 'Vaša pretplata je otkazana. Obnovite je da biste prijavili kvar.',
            default => 'Vaša pretplata nije aktivna. Prijava kvara je moguća kad pretplata krene.',
        };
    }
}
