<?php

namespace App\Http\Resources;

use App\Models\Invoice;
use App\Models\JobItem;
use App\Models\JobMaterial;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Racun uz nalog: rad i materijal razdvojeni, kako ih klijent i vidi.
 *
 * @mixin Invoice
 */
class JobInvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $job = $this->resource->job;
        $job?->loadMissing(['items', 'materials']);

        /** @var Collection<int, JobItem> $items */
        $items = $job?->items ?? collect();
        /** @var Collection<int, JobMaterial> $materials */
        $materials = $job?->materials ?? collect();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status->value,
            'labor_items' => $items->map(fn (JobItem $item) => [
                'name' => $item->name,
                'qty' => (int) $item->qty,
                'line_total' => (float) $item->line_total,
            ])->values(),
            'materials' => $materials->map(fn (JobMaterial $material) => [
                'name' => $material->name,
                'qty' => (float) $material->qty,
                'line_total' => (float) $material->line_total,
            ])->values(),
            'labor_total' => (float) $this->labor_total,
            'material_total' => (float) $this->material_total,
            'total' => (float) $this->total,
            'paid_at' => $this->paid_at?->toIso8601String(),
        ];
    }
}
