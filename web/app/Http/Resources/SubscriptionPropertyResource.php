<?php

namespace App\Http\Resources;

use App\Models\SubscriptionProperty;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SubscriptionProperty
 */
class SubscriptionPropertyResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'city_id' => $this->city_id,
            'city' => $this->whenLoaded('city', fn () => [
                'id' => $this->city->id,
                'name' => $this->city->name,
            ]),
            'street' => $this->street,
            'use' => $this->use->value,
            'contact_name' => $this->contact_name,
            'contact_note' => $this->contact_note,
            'remaining_visits' => $this->remaining_visits,
            'remaining_inspections' => $this->remaining_inspections,
        ];
    }
}
