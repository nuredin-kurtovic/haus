<?php

namespace App\Http\Resources;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Pun oblik pretplate: paket, datumi, prava po adresi.
 *
 * @mixin Subscription
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @param  int|null  $price  izracunata godisnja cijena, kad jos nije placena
     */
    public function __construct($resource, private ?int $price = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['package', 'properties.city']);

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'package' => [
                'id' => $this->package->id,
                'name' => $this->package->name,
                'slug' => $this->package->slug,
                'is_per_apartment' => (bool) $this->package->is_per_apartment,
            ],
            'starts_at' => $this->starts_at?->toIso8601String(),
            'ends_at' => $this->ends_at?->toIso8601String(),
            'auto_renew' => (bool) $this->auto_renew,
            'price' => $this->price ?? ($this->price_paid !== null ? (int) round((float) $this->price_paid) : null),
            'price_paid' => $this->price_paid !== null ? (float) $this->price_paid : null,
            'free_interventions' => (int) $this->free_interventions,
            'remaining_visits' => (int) $this->properties->sum('remaining_visits'),
            'remaining_inspections' => (int) $this->properties->sum('remaining_inspections'),
            'properties' => SubscriptionPropertyResource::collection($this->properties),
        ];
    }
}
