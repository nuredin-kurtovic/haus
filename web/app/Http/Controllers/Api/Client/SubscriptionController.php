<?php

namespace App\Http\Controllers\Api\Client;

use App\Http\Controllers\Controller;
use App\Http\Resources\PackageResource;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Pretplata sa pravima po adresi i historijom faktura.
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $subscription = $user->currentSubscription();

        if (! $subscription) {
            return response()->json(['message' => 'Pretplatu nismo pronašli.'], 404);
        }

        $subscription->loadMissing(['package', 'properties.city']);

        $tiers = $this->settings->get('pro_volume_tiers', []);

        $invoices = Invoice::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->get();

        return response()->json([
            'package' => new PackageResource($subscription->package, is_array($tiers) ? $tiers : []),
            'status' => $subscription->status->value,
            'starts_at' => $subscription->starts_at?->toIso8601String(),
            'ends_at' => $subscription->ends_at?->toIso8601String(),
            'auto_renew' => (bool) $subscription->auto_renew,
            'price_paid' => $subscription->price_paid !== null ? (float) $subscription->price_paid : null,
            'free_interventions' => (int) $subscription->free_interventions,
            'properties' => $subscription->properties->map(fn (SubscriptionProperty $property) => [
                'id' => $property->id,
                'city' => $property->city?->name,
                'street' => $property->street,
                'use' => $property->use->value,
                'remaining_visits' => (int) $property->remaining_visits,
                'remaining_inspections' => (int) $property->remaining_inspections,
            ])->values(),
            'payments' => $invoices->map(fn (Invoice $invoice) => [
                'number' => $invoice->number,
                'type' => $invoice->type->value,
                'total' => (float) $invoice->total,
                'status' => $invoice->status->value,
                'paid_at' => $invoice->paid_at?->toIso8601String(),
                'created_at' => $invoice->created_at?->toIso8601String(),
            ])->values(),
        ]);
    }

    /**
     * Otkazivanje je jedan klik: gasi se automatska obnova, pretplata vrijedi
     * do isteka. Ponovljen poziv nista ne mijenja.
     */
    public function cancel(Request $request): JsonResponse
    {
        $subscription = $request->user()->currentSubscription();

        if (! $subscription) {
            return response()->json(['message' => 'Pretplatu nismo pronašli.'], 404);
        }

        if ($subscription->auto_renew) {
            $subscription->update(['auto_renew' => false]);
        }

        return response()->json([
            'message' => $this->poruka($subscription),
            'auto_renew' => false,
            'ends_at' => $subscription->ends_at?->toIso8601String(),
        ]);
    }

    private function poruka(Subscription $subscription): string
    {
        if (! $subscription->ends_at) {
            return 'Automatska obnova je isključena. Pretplata se neće naplatiti ponovo.';
        }

        return 'Automatska obnova je isključena. Pretplata vrijedi do '
            .$subscription->ends_at->format('d.m.Y').'.';
    }
}
