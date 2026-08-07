<?php

namespace App\Services;

use App\Models\Package;
use Illuminate\Support\Str;

/**
 * Jedini izvor istine za izracun cijena.
 *
 * Snizene cijene se nikad ne cuvaju u bazi. Racunaju se iz osnovne cijene
 * i popusta paketa i zaokruzuju na cijeli KM.
 */
class PriceCalculator
{
    /**
     * Cijena rada za pretplatnika, zaokruzena na cijeli KM.
     */
    public function subscriberPrice(float $base, int $discountPct): int
    {
        return (int) round($base * (1 - $discountPct / 100));
    }

    /**
     * Cijena materijala: nabavna + marza, pa popust paketa na materijal.
     * Materijal se ne zaokruzuje na cijeli KM, ide na dvije decimale.
     */
    public function materialPrice(float $purchasePrice, int $markupPct = 20, int $discountPct = 0): float
    {
        $withMarkup = $purchasePrice * (1 + $markupPct / 100);

        return round($withMarkup * (1 - $discountPct / 100), 2);
    }

    /**
     * Ukupan iznos stavke materijala.
     */
    public function materialLineTotal(float $purchasePrice, float $qty, int $markupPct = 20, int $discountPct = 0): float
    {
        return round($this->materialPrice($purchasePrice, $markupPct, $discountPct) * $qty, 2);
    }

    /**
     * Ukupan iznos stavke rada.
     */
    public function laborLineTotal(float $base, int $qty, int $discountPct): int
    {
        return $this->subscriberPrice($base, $discountPct) * $qty;
    }

    /**
     * Kljuc paketa za API odgovor: haus-mini > mini.
     */
    public function packageKey(Package $package): string
    {
        return Str::of($package->slug)->after('haus-')->value() ?: $package->slug;
    }

    /**
     * Cijene jedne pozicije po svim paketima.
     *
     * @param  iterable<int, Package>  $packages
     * @return array<string, int>
     */
    public function pricesForPackages(float $base, iterable $packages): array
    {
        $prices = [];

        foreach ($packages as $package) {
            $prices[$this->packageKey($package)] = $this->subscriberPrice($base, $package->labor_discount_pct);
        }

        return $prices;
    }

    /**
     * Popust na kolicinu za HAUS Pro, u procentima.
     *
     * @param  array<int, array{min: int, max: int|null, pct: int}>  $tiers
     */
    public function volumeDiscountPct(int $apartments, array $tiers): int
    {
        foreach ($tiers as $tier) {
            $min = (int) ($tier['min'] ?? 0);
            $max = isset($tier['max']) ? (int) $tier['max'] : null;

            if ($apartments >= $min && ($max === null || $apartments <= $max)) {
                return (int) ($tier['pct'] ?? 0);
            }
        }

        return 0;
    }

    /**
     * Godisnja cijena pretplate, sa popustom na kolicinu za pakete po stanu.
     *
     * @param  array<int, array{min: int, max: int|null, pct: int}>  $tiers
     */
    public function subscriptionTotal(Package $package, int $apartments = 1, array $tiers = []): int
    {
        if (! $package->is_per_apartment) {
            return (int) round((float) $package->price_year);
        }

        $gross = (float) $package->price_year * $apartments;
        $pct = $this->volumeDiscountPct($apartments, $tiers);

        return (int) round($gross * (1 - $pct / 100));
    }
}
