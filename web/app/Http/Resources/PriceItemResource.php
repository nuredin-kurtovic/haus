<?php

namespace App\Http\Resources;

use App\Models\Package;
use App\Models\PriceItem;
use App\Services\PriceCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin PriceItem
 */
class PriceItemResource extends JsonResource
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
        $calculator = app(PriceCalculator::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit,
            'base_price' => (float) $this->base_price,
            'prices' => $calculator->pricesForPackages((float) $this->base_price, $this->packages),
        ];
    }
}
