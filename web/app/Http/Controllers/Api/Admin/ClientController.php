<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\JobStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminJobResource;
use App\Models\HomeRecord;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    /** Koliko klijenata stane na stranicu. */
    private const PO_STRANICI = 20;

    /**
     * Klijenti sa sazetkom pretplate. Pretraga ide po imenu, mejlu i adresi.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $q = trim((string) ($validated['q'] ?? ''));

        $stranica = User::query()
            ->role('klijent')
            ->when($q !== '', fn ($query) => $query->where(function ($where) use ($q) {
                $where->where('name', 'like', '%'.$q.'%')
                    ->orWhere('email', 'like', '%'.$q.'%')
                    ->orWhereHas('subscriptions.properties', fn ($property) => $property->where('street', 'like', '%'.$q.'%'));
            }))
            ->with(['subscriptions.package', 'subscriptions.properties'])
            ->withCount([
                'jobs',
                'jobs as active_jobs_count' => fn ($query) => $query->where('status', '!=', JobStatus::Zavrseno->value),
            ])
            ->orderBy('name')
            ->paginate((int) ($validated['per_page'] ?? self::PO_STRANICI));

        return response()->json([
            'data' => $stranica->getCollection()->map(function (User $user): array {
                $subscription = $user->currentSubscription();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'package' => $subscription?->package?->name,
                    'subscription_status' => $subscription?->status->value,
                    'ends_at' => $subscription?->ends_at?->toIso8601String(),
                    'properties_count' => (int) ($subscription?->properties->count() ?? 0),
                    'jobs_count' => (int) $user->jobs_count,
                    'active_jobs_count' => (int) $user->active_jobs_count,
                    'created_at' => $user->created_at?->toIso8601String(),
                ];
            })->values(),
            'meta' => [
                'total' => $stranica->total(),
                'per_page' => $stranica->perPage(),
                'current_page' => $stranica->currentPage(),
                'last_page' => $stranica->lastPage(),
            ],
        ]);
    }

    /**
     * Karton klijenta: pretplate, adrese, nalozi, fakture i hronologija doma.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $user = User::query()
            ->with(['subscriptions.package', 'subscriptions.properties.city'])
            ->find($id);

        if (! $user) {
            return response()->json(['message' => 'Klijent nije pronađen.'], 404);
        }

        $propertyIds = $user->subscriptions
            ->flatMap(fn (Subscription $subscription) => $subscription->properties->pluck('id'))
            ->all();

        $jobs = Job::query()
            ->where('user_id', $user->id)
            ->with(['category', 'technician', 'user', 'property.city'])
            ->orderByDesc('id')
            ->get();

        $records = HomeRecord::query()
            ->whereIn('subscription_property_id', $propertyIds)
            ->with(['job', 'property'])
            ->orderByDesc('recorded_at')
            ->get();

        return response()->json([
            'data' => [
                'client' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'notifications' => [
                        'push' => (bool) $user->notif_push,
                        'email' => (bool) $user->notif_email,
                        'marketing' => (bool) $user->notif_marketing,
                    ],
                    'created_at' => $user->created_at?->toIso8601String(),
                ],
                'subscriptions' => $user->subscriptions->map(fn (Subscription $subscription) => [
                    'id' => $subscription->id,
                    'status' => $subscription->status->value,
                    'package' => $subscription->package?->name,
                    'starts_at' => $subscription->starts_at?->toIso8601String(),
                    'ends_at' => $subscription->ends_at?->toIso8601String(),
                    'auto_renew' => (bool) $subscription->auto_renew,
                    'price_paid' => $subscription->price_paid !== null ? (float) $subscription->price_paid : null,
                    'free_interventions' => (int) $subscription->free_interventions,
                ])->values(),
                'properties' => $user->subscriptions
                    ->flatMap(fn (Subscription $subscription) => $subscription->properties)
                    ->map(fn (SubscriptionProperty $property) => [
                        'id' => $property->id,
                        'city' => $property->city?->name,
                        'street' => $property->street,
                        'use' => $property->use->value,
                        'contact_name' => $property->contact_name,
                        'contact_note' => $property->contact_note,
                        'remaining_visits' => (int) $property->remaining_visits,
                        'remaining_inspections' => (int) $property->remaining_inspections,
                    ])->values(),
                'jobs' => AdminJobResource::collection($jobs),
                'invoices' => Invoice::query()
                    ->where('user_id', $user->id)
                    ->orderByDesc('id')
                    ->get()
                    ->map(fn (Invoice $invoice) => [
                        'id' => $invoice->id,
                        'number' => $invoice->number,
                        'type' => $invoice->type->value,
                        'status' => $invoice->status->value,
                        'total' => (float) $invoice->total,
                        'paid_at' => $invoice->paid_at?->toIso8601String(),
                        'created_at' => $invoice->created_at?->toIso8601String(),
                    ])->values(),
                'home_records' => $records->map(fn (HomeRecord $record) => [
                    'id' => $record->id,
                    'type' => $record->type->value,
                    'title' => $record->title,
                    'body' => $record->body,
                    'recorded_at' => $record->recorded_at?->toIso8601String(),
                    'job_number' => $record->job?->number,
                    'street' => $record->property?->street,
                ])->values(),
            ],
        ]);
    }
}
