<?php

namespace Tests\Feature\Api;

use App\Models\Package;
use App\Models\PriceItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_paketi_vracaju_200_i_ocekivan_oblik(): void
    {
        $response = $this->getJson('/api/v1/packages');

        $response->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'slug', 'price_year', 'visits_per_year',
                        'deadline_hours', 'emergency_deadline_hours', 'emergency_included',
                        'labor_discount_pct', 'material_discount_pct', 'inspections_per_year',
                        'warranty_months', 'is_per_apartment', 'sort', 'volume_discount_tiers',
                    ],
                ],
            ]);

        $packages = collect($response->json('data'))->keyBy('slug');

        $this->assertEqualsWithDelta(59, $packages['haus-mini']['price_year'], 0.001);
        $this->assertSame(72, $packages['haus-mini']['deadline_hours']);
        $this->assertSame(24, $packages['haus-mini']['emergency_deadline_hours']);
        $this->assertFalse($packages['haus-mini']['emergency_included']);
        $this->assertSame(15, $packages['haus-mini']['labor_discount_pct']);
        $this->assertSame([], $packages['haus-mini']['volume_discount_tiers']);

        $this->assertEqualsWithDelta(169, $packages['haus-plus']['price_year'], 0.001);
        $this->assertSame(12, $packages['haus-plus']['emergency_deadline_hours']);
        $this->assertTrue($packages['haus-plus']['emergency_included']);

        $this->assertEqualsWithDelta(390, $packages['haus-pro']['price_year'], 0.001);
        $this->assertTrue($packages['haus-pro']['is_per_apartment']);
        $this->assertSame(
            [
                ['min' => 2, 'max' => 4, 'pct' => 10],
                ['min' => 5, 'max' => 9, 'pct' => 15],
            ],
            $packages['haus-pro']['volume_discount_tiers']
        );
    }

    public function test_gradovi_vracaju_200_i_ocekivan_oblik(): void
    {
        $response = $this->getJson('/api/v1/cities');

        $response->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonStructure(['data' => ['*' => ['id', 'name', 'lat', 'lng', 'status']]]);

        $cities = collect($response->json('data'))->keyBy('name');

        $this->assertSame('aktivan', $cities['Sarajevo']['status']);
        $this->assertSame('aktivan', $cities['Travnik']['status']);
        $this->assertSame('u_pripremi', $cities['Mostar']['status']);
        $this->assertEqualsWithDelta(43.8563, $cities['Sarajevo']['lat'], 0.0001);
        $this->assertEqualsWithDelta(18.4131, $cities['Sarajevo']['lng'], 0.0001);
    }

    public function test_cjenovnik_vraca_200_sa_izracunatim_cijenama(): void
    {
        $response = $this->getJson('/api/v1/price-list');

        $response->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'name', 'slug', 'icon',
                        'items' => [
                            '*' => ['id', 'name', 'unit', 'base_price', 'prices' => ['mini', 'plus', 'pro']],
                        ],
                    ],
                ],
                'meta' => ['price_list_version'],
            ]);

        $items = collect($response->json('data'))->flatMap(fn ($category) => $category['items']);

        $this->assertSame(PriceItem::where('active', true)->count(), $items->count());
        $this->assertSame(58, $items->count());

        foreach ($items as $item) {
            $this->assertIsInt($item['prices']['mini']);
            $this->assertIsInt($item['prices']['plus']);
            $this->assertIsInt($item['prices']['pro']);
        }

        $sifon = $items->firstWhere('name', 'Zamjena sifona');

        $this->assertNotNull($sifon);
        $this->assertEqualsWithDelta(40, $sifon['base_price'], 0.001);
        $this->assertSame(34, $sifon['prices']['mini']);
        $this->assertSame(30, $sifon['prices']['plus']);
        $this->assertSame(30, $sifon['prices']['pro']);
    }

    public function test_cjenovnik_filtrira_po_pretrazi(): void
    {
        $response = $this->getJson('/api/v1/price-list?q=sifon');

        $response->assertOk();

        $items = collect($response->json('data'))->flatMap(fn ($category) => $category['items']);

        $this->assertGreaterThan(0, $items->count());

        foreach ($items as $item) {
            $this->assertStringContainsStringIgnoringCase('sifon', $item['name']);
        }
    }

    public function test_cjenovnik_filtrira_po_kategoriji(): void
    {
        $response = $this->getJson('/api/v1/price-list?category=grijanje');

        $response->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame('Grijanje', $response->json('data.0.name'));
        $this->assertCount(8, $response->json('data.0.items'));
    }

    public function test_cjenovnik_ne_prikazuje_neaktivne_pozicije(): void
    {
        PriceItem::where('name', 'Zamjena sifona')->update(['active' => false]);

        $response = $this->getJson('/api/v1/price-list');

        $items = collect($response->json('data'))->flatMap(fn ($category) => $category['items']);

        $this->assertNull($items->firstWhere('name', 'Zamjena sifona'));
        $this->assertSame(57, $items->count());
    }

    public function test_cjenovnik_vraca_objavljenu_cijenu_a_ne_draft(): void
    {
        PriceItem::where('name', 'Zamjena sifona')->update(['draft_base_price' => 99]);

        $response = $this->getJson('/api/v1/price-list');

        $items = collect($response->json('data'))->flatMap(fn ($category) => $category['items']);

        $this->assertEqualsWithDelta(40, $items->firstWhere('name', 'Zamjena sifona')['base_price'], 0.001);
    }

    public function test_doplate_vracaju_200_i_ocekivan_oblik(): void
    {
        $response = $this->getJson('/api/v1/surcharges');

        $response->assertOk()
            ->assertJsonCount(7, 'data')
            ->assertJsonStructure(['data' => ['*' => ['key', 'label', 'type', 'value']]]);

        $surcharges = collect($response->json('data'))->keyBy('key');

        $this->assertSame('percent', $surcharges['hitno_radni_dan']['type']);
        $this->assertEqualsWithDelta(30, $surcharges['hitno_radni_dan']['value'], 0.001);
        $this->assertSame('per_km', $surcharges['izvan_grada']['type']);
        $this->assertEqualsWithDelta(1.2, $surcharges['izvan_grada']['value'], 0.001);
        $this->assertSame('flat', $surcharges['uzaludan_izlazak']['type']);
        $this->assertEqualsWithDelta(35, $surcharges['uzaludan_izlazak']['value'], 0.001);
    }

    public function test_javne_postavke_vracaju_200_i_ocekivan_oblik(): void
    {
        $response = $this->getJson('/api/v1/settings/public');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'radno_vrijeme' => ['pon_pet' => ['od', 'do'], 'subota' => ['od', 'do'], 'nedjelja'],
                    'satnica_redovna',
                    'satnica_hitna',
                    'izlazak_bez_pretplate',
                    'ukljuceno_minuta',
                    'materijal_marza_pct',
                    'price_list_version',
                ],
            ]);

        $this->assertEqualsWithDelta(40, $response->json('data.satnica_redovna'), 0.001);
        $this->assertEqualsWithDelta(70, $response->json('data.satnica_hitna'), 0.001);
        $this->assertEqualsWithDelta(35, $response->json('data.izlazak_bez_pretplate'), 0.001);
        $this->assertSame(45, $response->json('data.ukljuceno_minuta'));
        $this->assertSame(1, $response->json('data.price_list_version'));
        $this->assertSame('08:00', $response->json('data.radno_vrijeme.pon_pet.od'));
    }

    public function test_javni_endpointi_rade_bez_auth_a(): void
    {
        foreach ([
            '/api/v1/packages',
            '/api/v1/cities',
            '/api/v1/price-list',
            '/api/v1/surcharges',
            '/api/v1/settings/public',
        ] as $url) {
            $this->getJson($url)->assertOk();
        }
    }

    public function test_neaktivan_paket_se_ne_vraca(): void
    {
        Package::where('slug', 'haus-mini')->update(['active' => false]);

        $this->getJson('/api/v1/packages')->assertOk()->assertJsonCount(2, 'data');
    }
}
