<?php

namespace App\Http\Resources;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Red u serviserovoj listi naloga. Bez cijena, majstor bira pozicije poslije.
 *
 * @mixin Job
 */
class TechnicianJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['category', 'user', 'property.city']);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'is_emergency' => (bool) $this->is_emergency,
            'category' => $this->category?->name,
            'description' => $this->description,
            'client' => ['name' => $this->user?->name],
            'address' => [
                'city' => $this->property?->city?->name,
                'street' => $this->property?->street,
            ],
            'scheduled_window_start' => $this->scheduled_window_start?->toIso8601String(),
            'scheduled_window_end' => $this->scheduled_window_end?->toIso8601String(),
            'deadline_at' => $this->deadline_at?->toIso8601String(),
        ];
    }
}
