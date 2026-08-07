<?php

namespace App\Http\Resources;

use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Red u klijentovoj listi naloga.
 *
 * @mixin Job
 */
class JobListResource extends JsonResource
{
    /** Naslov kartice je pocetak opisa, pun opis ide u detalj. */
    public const NASLOV_ZNAKOVA = 60;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['category', 'technician']);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'type' => $this->type->value,
            'category' => $this->category?->name,
            'title' => Str::limit((string) $this->description, self::NASLOV_ZNAKOVA),
            'description' => $this->description,
            'is_emergency' => (bool) $this->is_emergency,
            'technician' => $this->technician ? ['name' => $this->technician->name] : null,
            'scheduled_window_start' => $this->scheduled_window_start?->toIso8601String(),
            'scheduled_window_end' => $this->scheduled_window_end?->toIso8601String(),
            'warranty_until' => $this->warranty_until?->toDateString(),
            'created_at' => $this->created_at?->toIso8601String(),
            'deadline_at' => $this->deadline_at?->toIso8601String(),
            'deadline_missed_at' => $this->deadline_missed_at?->toIso8601String(),
        ];
    }
}
