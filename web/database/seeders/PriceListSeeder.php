<?php

namespace Database\Seeders;

use App\Models\PriceCategory;
use App\Models\PriceItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cjenovnik rada, sa PDV-om, u KM. Osnovna cijena je cijena bez pretplate.
 * Cijene za pretplatnike se racunaju kroz PriceCalculator i nikad se ne cuvaju.
 */
class PriceListSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->categories() as $sort => $category) {
            $model = PriceCategory::updateOrCreate(
                ['slug' => Str::slug($category['name'])],
                [
                    'name' => $category['name'],
                    'slug' => Str::slug($category['name']),
                    'icon' => $category['icon'],
                    'sort' => $sort + 1,
                ]
            );

            foreach ($category['items'] as $itemSort => [$name, $price]) {
                PriceItem::updateOrCreate(
                    ['price_category_id' => $model->id, 'name' => $name],
                    [
                        'base_price' => $price,
                        'draft_base_price' => null,
                        'unit' => 'pozicija',
                        'sort' => $itemSort + 1,
                        'active' => true,
                    ]
                );
            }
        }
    }

    /**
     * @return array<int, array{name: string, icon: string, items: array<int, array{0: string, 1: float}>}>
     */
    private function categories(): array
    {
        return [
            [
                'name' => 'Vodoinstalacije',
                'icon' => 'voda',
                'items' => [
                    ['Zamjena baterije (lavabo ili sudopera)', 55],
                    ['Zamjena tuš baterije', 60],
                    ['Zamjena sifona', 40],
                    ['Zamjena ugaonog ventila', 40],
                    ['Odčepljenje lavaboa ili sudopere', 55],
                    ['Odčepljenje mašinom (kanalizacija)', 130],
                    ['Zamjena mehanizma vodokotlića', 65],
                    ['Zamjena WC šolje', 120],
                    ['Traženje i sanacija curenja u zidu', 190],
                    ['Zamjena bojlera (rad)', 120],
                    ['Servis bojlera: grijač, anoda, čišćenje', 95],
                    ['Priključenje mašine za pranje ili suđe', 50],
                    ['Silikoniranje kade ili tuš kabine', 60],
                    ['Zamjena crijeva i priključaka pod sudoperom', 45],
                ],
            ],
            [
                'name' => 'Elektroinstalacije',
                'icon' => 'struja',
                'items' => [
                    ['Zamjena prekidača ili utičnice', 35],
                    ['Zamjena svjetiljke ili lustera', 45],
                    ['Montaža LED panela ili spota', 40],
                    ['Zamjena automatskog osigurača', 40],
                    ['Traženje prekida u instalaciji', 90],
                    ['Nova utičnica sa štemanjem i gletovanjem', 85],
                    ['Zamjena razvodne table (do 12 mjesta)', 240],
                    ['Ugradnja FID zaštitne sklopke', 110],
                    ['Električno povezivanje bojlera', 80],
                    ['Ugradnja ventilatora u kupatilu', 80],
                    ['Ugradnja video-portafona (stan)', 130],
                    ['Provjera i mjerenje instalacije sa nalazom', 120],
                ],
            ],
            [
                'name' => 'Grijanje',
                'icon' => 'grijanje',
                'items' => [
                    ['Odzračivanje sistema (do 5 radijatora)', 45],
                    ['Zamjena radijatorskog ventila', 60],
                    ['Zamjena termostatske glave', 45],
                    ['Demontaža, pranje i montaža radijatora', 120],
                    ['Zamjena radijatora (isti priključci)', 130],
                    ['Godišnji servis etažnog kotla', 130],
                    ['Servis peći na pelet', 140],
                    ['Zamjena cirkulacione pumpe', 110],
                ],
            ],
            [
                'name' => 'Klima uređaji',
                'icon' => 'klima',
                'items' => [
                    ['Servis i dubinsko čišćenje (1 jedinica)', 70],
                    ['Provjera pritiska i dopuna freona', 120],
                    ['Montaža (do 3 m cijevi, bez bušenja betona)', 200],
                    ['Svaki dodatni metar cijevi', 25],
                    ['Demontaža uređaja', 100],
                    ['Premještanje na drugu poziciju', 260],
                    ['Odčepljenje odvoda kondenzata', 50],
                ],
            ],
            [
                'name' => 'Bravarija i stolarija',
                'icon' => 'brava',
                'items' => [
                    ['Zamjena cilindra brave', 55],
                    ['Otvaranje zaključanih vrata (bez štete)', 110],
                    ['Podešavanje ulaznih vrata', 50],
                    ['Podešavanje PVC prozora ili balkonskih vrata', 40],
                    ['Zamjena okova na prozoru', 90],
                    ['Zamjena gume ili dihtunga po prozoru', 45],
                    ['Servis roletne (gurtna, mehanizam)', 70],
                    ['Zamjena roletne (rad)', 120],
                    ['Montaža sigurnosne brave', 130],
                ],
            ],
            [
                'name' => 'Sitni poslovi',
                'icon' => 'alat',
                'items' => [
                    ['Montaža police, slike, ogledala (do 3 kom)', 40],
                    ['Montaža TV nosača na zid', 65],
                    ['Montaža namještaja, po satu', 40],
                    ['Montaža kuhinjskih elemenata, po satu', 45],
                    ['Montaža karniše ili šipke', 40],
                    ['Zamjena šarki na kuhinjskim vratima', 40],
                    ['Bušenje betona ili armature, po otvoru', 20],
                    ['Sitne popravke, paket 2 sata po dogovoru', 75],
                ],
            ],
        ];
    }
}
