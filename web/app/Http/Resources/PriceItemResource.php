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
     * @param  Package|null  $myPackage  paket prijavljenog klijenta, dodaje my_price
     */
    public function __construct($resource, private Collection $packages, private ?Package $myPackage = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $calculator = app(PriceCalculator::class);
        $base = (float) $this->base_price;

        $payload = [
            'id' => $this->id,
            'name' => $this->name,
            'unit' => $this->unit,
            'base_price' => $base,
            'prices' => $calculator->pricesForPackages($base, $this->packages),
        ];

        if ($this->myPackage) {
            $payload['my_price'] = $calculator->subscriberPrice($base, $this->myPackage->labor_discount_pct);
        }

        return $payload;
    }
}
