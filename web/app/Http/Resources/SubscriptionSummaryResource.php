<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Sazetak aktivne pretplate za /me. Izlasci su zbir preko svih adresa.
 *
 * @mixin Subscription
 */
class SubscriptionSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['package', 'properties']);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'package' => [
                'id' => $this->package->id,
                'name' => $this->package->name,
                'slug' => $this->package->slug,
            ],
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'remaining_visits' => (int) $this->properties->sum('remaining_visits'),
            'remaining_inspections' => (int) $this->properties->sum('remaining_inspections'),
            'free_interventions' => (int) $this->free_interventions,
            'properties_count' => $this->properties->count(),
        ];
    }
}
