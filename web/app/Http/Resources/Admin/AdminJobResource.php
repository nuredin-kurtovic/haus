<?php

namespace App\Http\Resources\Admin;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Red u dispecerskoj listi naloga.
 *
 * @mixin Job
 */
class AdminJobResource extends JsonResource
{
    /** Naslov kartice je pocetak opisa. */
    public const NASLOV_ZNAKOVA = 60;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['category', 'technician', 'user', 'property.city']);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'is_emergency' => (bool) $this->is_emergency,
            'category' => $this->category?->name,
            'title' => Str::limit((string) $this->description, self::NASLOV_ZNAKOVA),
            'client' => [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'email' => $this->user?->email,
            ],
            'address' => [
                'city' => $this->property?->city?->name,
                'street' => $this->property?->street,
            ],
            'technician' => $this->technician ? [
                'id' => $this->technician->id,
                'name' => $this->technician->name,
            ] : null,
            'scheduled_window_start' => $this->scheduled_window_start?->toIso8601String(),
            'scheduled_window_end' => $this->scheduled_window_end?->toIso8601String(),
            'deadline_at' => $this->deadline_at?->toIso8601String(),
            'deadline_missed_at' => $this->deadline_missed_at?->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
