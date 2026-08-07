<?php

namespace App\Http\Resources;

use App\Models\Package;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Package
 */
class PackageResource extends JsonResource
{
    /**
     * @param  array<int, array{min: int, max: int|null, pct: int}>  $volumeTiers
     */
    public function __construct($resource, private array $volumeTiers = [])
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'price_year' => (float) $this->price_year,
            'visits_per_year' => $this->visits_per_year,
            'deadline_hours' => $this->deadline_hours,
            'emergency_deadline_hours' => $this->emergency_deadline_hours,
            'emergency_included' => $this->emergency_included,
            'labor_discount_pct' => $this->labor_discount_pct,
            'material_discount_pct' => $this->material_discount_pct,
            'inspections_per_year' => $this->inspections_per_year,
            'warranty_months' => $this->warranty_months,
            'is_per_apartment' => $this->is_per_apartment,
            'sort' => $this->sort,
            'volume_discount_tiers' => $this->is_per_apartment ? $this->volumeTiers : [],
        ];
    }
}
