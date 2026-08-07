<?php

namespace Tests\Unit\Services;

use App\Models\Package;
use App\Services\PriceCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PriceCalculatorTest extends TestCase
{
    private PriceCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new PriceCalculator;
    }

    public function test_osnovna_cijena_59_sa_popustom_25_daje_44(): void
    {
        $this->assertSame(44, $this->calculator->subscriberPrice(59, 25));
    }

    public function test_osnovna_cijena_59_sa_popustom_15_daje_50(): void
    {
        $this->assertSame(50, $this->calculator->subscriberPrice(59, 15));
    }

    public function test_bez_popusta_cijena_ostaje_ista(): void
    {
        $this->assertSame(120, $this->calculator->subscriberPrice(120, 0));
    }

    #[DataProvider('zaokruzivanjeProvider')]
    public function test_zaokruzivanje_na_cijeli_km(float $base, int $pct, int $expected): void
    {
        $this->assertSame($expected, $this->calculator->subscriberPrice($base, $pct));
    }

    /**
     * @return array<string, array{0: float, 1: int, 2: int}>
     */
    public static function zaokruzivanjeProvider(): array
    {
        return [
            // 35 * 0.85 = 29.75
            '29.75 ide na 30' => [35.0, 15, 30],
            // 45 * 0.75 = 33.75
            '33.75 ide na 34' => [45.0, 25, 34],
            // 55 * 0.85 = 46.75
            '46.75 ide na 47' => [55.0, 15, 47],
            // 240 * 0.75 = 180
            'tacan broj ostaje' => [240.0, 25, 180],
            // 25 * 0.75 = 18.75
            '18.75 ide na 19' => [25.0, 25, 19],
        ];
    }

    public function test_rezultat_je_uvijek_cijeli_broj(): void
    {
        $this->assertIsInt($this->calculator->subscriberPrice(190, 25));
    }

    public function test_materijal_je_nabavna_plus_marza_pa_popust(): void
    {
        // 100 * 1.20 = 120, pa 5 posto popusta = 114.
        $this->assertSame(114.0, $this->calculator->materialPrice(100, 20, 5));
        $this->assertSame(120.0, $this->calculator->materialPrice(100, 20, 0));
        $this->assertSame(60.0, $this->calculator->materialPrice(50, 20, 0));
    }

    public function test_ukupan_iznos_stavke_materijala(): void
    {
        $this->assertSame(240.0, $this->calculator->materialLineTotal(100, 2, 20, 0));
    }

    public function test_ukupan_iznos_stavke_rada(): void
    {
        $this->assertSame(88, $this->calculator->laborLineTotal(59, 2, 25));
    }

    public function test_cijene_po_paketima(): void
    {
        $prices = $this->calculator->pricesForPackages(59, [
            $this->package('haus-mini', 15),
            $this->package('haus-plus', 25),
            $this->package('haus-pro', 25),
        ]);

        $this->assertSame(['mini' => 50, 'plus' => 44, 'pro' => 44], $prices);
    }

    public function test_pro_popust_na_kolicinu(): void
    {
        $tiers = [
            ['min' => 2, 'max' => 4, 'pct' => 10],
            ['min' => 5, 'max' => 9, 'pct' => 15],
        ];

        $this->assertSame(0, $this->calculator->volumeDiscountPct(1, $tiers));
        $this->assertSame(10, $this->calculator->volumeDiscountPct(3, $tiers));
        $this->assertSame(15, $this->calculator->volumeDiscountPct(7, $tiers));
        $this->assertSame(0, $this->calculator->volumeDiscountPct(12, $tiers));
    }

    public function test_ukupna_cijena_pretplate(): void
    {
        $tiers = [
            ['min' => 2, 'max' => 4, 'pct' => 10],
            ['min' => 5, 'max' => 9, 'pct' => 15],
        ];

        $plus = new Package(['slug' => 'haus-plus', 'price_year' => 169, 'is_per_apartment' => false]);
        $pro = new Package(['slug' => 'haus-pro', 'price_year' => 390, 'is_per_apartment' => true]);

        $this->assertSame(169, $this->calculator->subscriptionTotal($plus, 1, $tiers));
        $this->assertSame(390, $this->calculator->subscriptionTotal($pro, 1, $tiers));
        // 390 * 3 = 1170, popust 10 posto.
        $this->assertSame(1053, $this->calculator->subscriptionTotal($pro, 3, $tiers));
    }

    private function package(string $slug, int $laborDiscountPct): Package
    {
        return new Package(['slug' => $slug, 'labor_discount_pct' => $laborDiscountPct]);
    }
}
