<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SubscriptionController extends Controller
{
    /**
     * Pretplate sa iskoristenoscu prava i datumom obnove.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(SubscriptionStatus::values())],
            'q' => ['nullable', 'string', 'max:255'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));

        $subscriptions = Subscription::query()
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($q !== '', fn ($query) => $query->whereHas('user', fn ($user) => $user->where('name', 'like', '%'.$q.'%')
                ->orWhere('email', 'like', '%'.$q.'%')))
            ->with(['user', 'package', 'properties'])
            ->orderByRaw('ends_at is null')
            ->orderBy('ends_at')
            ->get();

        return response()->json([
            'data' => $subscriptions->map(function (Subscription $subscription): array {
                $properties = $subscription->properties;
                $package = $subscription->package;

                $visitsTotal = (int) ($package?->visits_per_year ?? 0) * $properties->count();
                $inspectionsTotal = (int) ($package?->inspections_per_year ?? 0) * $properties->count();
                $remainingVisits = (int) $properties->sum('remaining_visits');
                $remainingInspections = (int) $properties->sum('remaining_inspections');

                return [
                    'id' => $subscription->id,
                    'status' => $subscription->status->value,
                    'client' => [
                        'id' => $subscription->user?->id,
                        'name' => $subscription->user?->name,
                        'email' => $subscription->user?->email,
                    ],
                    'package' => [
                        'id' => $package?->id,
                        'name' => $package?->name,
                        'slug' => $package?->slug,
                    ],
                    'starts_at' => $subscription->starts_at?->toIso8601String(),
                    'ends_at' => $subscription->ends_at?->toIso8601String(),
                    'auto_renew' => (bool) $subscription->auto_renew,
                    'price_paid' => $subscription->price_paid !== null ? (float) $subscription->price_paid : null,
                    'properties_count' => $properties->count(),
                    'free_interventions' => (int) $subscription->free_interventions,
                    'usage' => [
                        'visits_total' => $visitsTotal,
                        'visits_remaining' => $remainingVisits,
                        'visits_used' => max(0, $visitsTotal - $remainingVisits),
                        'inspections_total' => $inspectionsTotal,
                        'inspections_remaining' => $remainingInspections,
                        'inspections_used' => max(0, $inspectionsTotal - $remainingInspections),
                    ],
                ];
            })->values(),
            'meta' => [
                'total' => $subscriptions->count(),
            ],
        ]);
    }
}
