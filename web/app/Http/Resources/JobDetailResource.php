<?php

namespace App\Http\Resources;

use App\Models\Job;
use App\Models\JobPhoto;
use Illuminate\Http\Request;

/**
 * Detalj naloga za klijenta: nalaz, fotografije i racun ako postoji.
 *
 * @mixin Job
 */
class JobDetailResource extends JobListResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing([
            'category', 'technician', 'photos', 'property.city',
            'invoice.job.items', 'invoice.job.materials',
        ]);

        return array_merge(parent::toArray($request), [
            'preferred_window' => $this->preferred_window,
            'completed_at' => $this->completed_at?->toIso8601String(),
            'findings' => $this->findings,
            'steps' => $this->steps(),
            'property' => $this->property ? [
                'id' => $this->property->id,
                'city' => $this->property->city?->name,
                'street' => $this->property->street,
            ] : null,
            'photos' => $this->photos
                ->map(fn (JobPhoto $photo) => [
                    'type' => $photo->type->value,
                    'url' => $photo->url(),
                ])
                ->values(),
            'invoice' => $this->invoice
                ? new JobInvoiceResource($this->invoice)
                : null,
        ]);
    }
}
