<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Models\PriceCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin PriceCategory
 */
class PriceCategoryResource extends JsonResource
{
    /**
     * @param  Collection<int, Package>  $packages
     */
    public function __construct($resource, private Collection $packages)
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
            'icon' => $this->icon,
            'items' => $this->items->map(
                fn ($item) => new PriceItemResource($item, $this->packages)
            )->values(),
        ];
    }
}
