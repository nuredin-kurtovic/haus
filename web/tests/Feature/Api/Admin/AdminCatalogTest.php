<?php

namespace Tests\Feature\Api\Admin;

use App\Enums\CityStatus;
use App\Models\City;
use App\Models\Job;
use App\Models\PriceCategory;
use App\Models\PriceItem;
use App\Models\Setting;
use App\Models\Surcharge;
use App\Models\Technician;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cjenovnik, gradovi, postavke, doplate i majstori. Sve su to podaci u bazi,
 * uredjivani iz admin panela.
 */
class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $dispecer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        $this->dispecer = User::where('email', 'dispecer@haus.ba')->firstOrFail();
    }

    private function kaoDispecer(): self
    {
        $this->actingAs($this->dispecer, 'sanctum');

        return $this;
    }

    public function test_cjenovnik_prikazuje_nacrt_i_oznaku_izmjene(): void
    {
        $item = PriceItem::query()->orderBy('id')->firstOrFail();

        $this->kaoDispecer()
            ->putJson('/api/v1/admin/price-list/items/'.$item->id, ['draft_base_price' => 61])
            ->assertOk()
            ->assertJsonPath('data.dirty', true)
            ->assertJsonPath('data.base_price', 55)
            ->assertJsonPath('data.draft_base_price', 61);

        $response = $this->kaoDispecer()
            ->getJson('/api/v1/admin/price-list')
            ->assertOk()
            ->assertJsonPath('meta.dirty_count', 1)
            ->assertJsonPath('meta.price_list_version', 1);

        $this->assertSame(61, $response->json('data.0.items.0.draft_base_price'));
        $this->assertSame(55, $response->json('data.0.items.0.base_price'));
        $this->assertTrue($response->json('data.0.items.0.dirty'));

        // Objavljena cijena se ne mijenja dok se cjenovnik ne objavi.
        $this->getJson('/api/v1/price-list')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.base_price', 55);
    }

    public function test_objava_prenosi_nacrte_i_dize_verziju(): void
    {
        $item = PriceItem::query()->orderBy('id')->firstOrFail();

        $this->kaoDispecer()
            ->putJson('/api/v1/admin/price-list/items/'.$item->id, ['draft_base_price' => 61])
            ->assertOk();

        $this->kaoDispecer()
            ->postJson('/api/v1/admin/price-list/publish')
            ->assertOk()
            ->assertJsonPath('data.published', 1)
            ->assertJsonPath('data.price_list_version', 2);

        $item->refresh();

        $this->assertSame(61.0, (float) $item->base_price);
        $this->assertNull($item->draft_base_price);

        $this->assertSame(2, (int) app(SettingsService::class)->get('price_list_version'));

        // Cijene za pretplatnike se racunaju iz nove osnovne cijene.
        $this->getJson('/api/v1/price-list')
            ->assertOk()
            ->assertJsonPath('data.0.items.0.base_price', 61)
            ->assertJsonPath('data.0.items.0.prices.plus', 46);
    }

    public function test_objava_bez_izmjena_ne_dize_broj_izmjena(): void
    {
        $this->kaoDispecer()
            ->postJson('/api/v1/admin/price-list/publish')
            ->assertOk()
            ->assertJsonPath('data.published', 0)
            ->assertJsonPath('data.price_list_version', 2);
    }

    public function test_negativna_cijena_se_odbija(): void
    {
        $item = PriceItem::query()->orderBy('id')->firstOrFail();

        $this->kaoDispecer()
            ->putJson('/api/v1/admin/price-list/items/'.$item->id, ['draft_base_price' => -5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('draft_base_price');
    }

    public function test_novi_grad_krece_u_pripremi(): void
    {
        $response = $this->kaoDispecer()
            ->postJson('/api/v1/admin/cities', [
                'name' => 'Banja Luka',
                'lat' => 44.7722,
                'lng' => 17.1910,
                // Status iz zahtjeva se ignorise, novi grad je uvijek u pripremi.
                'status' => 'aktivan',
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'u_pripremi')
            ->assertJsonPath('data.slug', 'banja-luka');

        $city = City::findOrFail($response->json('data.id'));

        $this->assertSame(CityStatus::UPripremi, $city->status);
    }

    public function test_grad_izvan_granica_bih_se_odbija(): void
    {
        $this->kaoDispecer()
            ->postJson('/api/v1/admin/cities', ['name' => 'Beč', 'lat' => 48.2082, 'lng' => 16.3738])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lat');

        $this->kaoDispecer()
            ->postJson('/api/v1/admin/cities', ['name' => 'Rim', 'lat' => 43.5, 'lng' => 12.5])
            ->assertStatus(422)
            ->assertJsonValidationErrors('lng');

        $this->assertSame(6, City::count());
    }

    public function test_status_grada_se_mijenja(): void
    {
        $zenica = City::where('slug', 'zenica')->firstOrFail();

        $this->kaoDispecer()
            ->patchJson('/api/v1/admin/cities/'.$zenica->id, ['status' => 'aktivan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'aktivan');

        $this->getJson('/api/v1/cities')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'zenica', 'status' => 'aktivan']);
    }

    public function test_grad_sa_adresama_se_ne_brise(): void
    {
        $sarajevo = City::where('slug', 'sarajevo')->firstOrFail();

        $this->kaoDispecer()
            ->deleteJson('/api/v1/admin/cities/'.$sarajevo->id)
            ->assertStatus(422)
            ->assertJsonPath('message', 'Grad ima upisane adrese pretplatnika i ne može se obrisati. Prebacite ga u pripremu.');

        $this->assertNotNull(City::find($sarajevo->id));
    }

    public function test_prazan_grad_se_brise(): void
    {
        $mostar = City::where('slug', 'mostar')->firstOrFail();

        $this->kaoDispecer()
            ->deleteJson('/api/v1/admin/cities/'.$mostar->id)
            ->assertOk();

        $this->assertNull(City::find($mostar->id));
    }

    public function test_postavke_se_citaju_i_snimaju(): void
    {
        $this->kaoDispecer()
            ->getJson('/api/v1/admin/settings')
            ->assertOk()
            ->assertJsonPath('data.satnica_redovna', 40)
            ->assertJsonPath('data.radno_vrijeme.pon_pet.od', '08:00')
            ->assertJsonCount(8, 'data.template_keys');

        $this->kaoDispecer()
            ->putJson('/api/v1/admin/settings', [
                'satnica_redovna' => 45,
                'radno_vrijeme' => [
                    'pon_pet' => ['od' => '07:30', 'do' => '18:00'],
                    'subota' => ['od' => '09:00', 'do' => '14:00'],
                    'nedjelja' => ['samo_hitno' => true],
                    'napomena' => 'Nedjeljom radimo samo hitne intervencije.',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.satnica_redovna', 45)
            ->assertJsonPath('data.radno_vrijeme.pon_pet.od', '07:30');

        $this->assertSame(45, app(SettingsService::class)->get('satnica_redovna'));
        // Kljucevi koji nisu poslani ostaju netaknuti.
        $this->assertSame(70, app(SettingsService::class)->get('satnica_hitna'));
    }

    public function test_predlozak_ne_smije_nestati(): void
    {
        $this->kaoDispecer()
            ->putJson('/api/v1/admin/settings', [
                'notification_templates' => ['zavrseno' => 'HAUS: Sređeno. Garancija do {garancija_datum}.'],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('notification_templates');

        $templates = Setting::where('key', 'notification_templates')->value('value');
        $this->assertCount(8, $templates);
    }

    public function test_em_dash_u_predlosku_se_odbija(): void
    {
        $templates = Setting::where('key', 'notification_templates')->value('value');
        $templates['zavrseno'] = 'HAUS: Sređeno '."\u{2014}".' garancija do {garancija_datum}.';

        $this->kaoDispecer()
            ->putJson('/api/v1/admin/settings', ['notification_templates' => $templates])
            ->assertStatus(422)
            ->assertJsonValidationErrors('notification_templates');
    }

    public function test_neispravno_radno_vrijeme_se_odbija(): void
    {
        $this->kaoDispecer()
            ->putJson('/api/v1/admin/settings', [
                'radno_vrijeme' => [
                    'pon_pet' => ['od' => 'osam', 'do' => '18:00'],
                    'subota' => ['od' => '09:00', 'do' => '14:00'],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('radno_vrijeme.pon_pet.od');
    }

    public function test_doplata_se_mijenja(): void
    {
        $doplata = Surcharge::where('key', 'nedjelja_praznik')->firstOrFail();

        $this->kaoDispecer()
            ->getJson('/api/v1/admin/surcharges')
            ->assertOk()
            ->assertJsonCount(7, 'data');

        $this->kaoDispecer()
            ->putJson('/api/v1/admin/surcharges/'.$doplata->id, ['value' => 80, 'active' => false])
            ->assertOk()
            ->assertJsonPath('data.value', 80)
            ->assertJsonPath('data.active', false);

        // Javni endpoint prikazuje samo aktivne doplate.
        $this->getJson('/api/v1/surcharges')
            ->assertOk()
            ->assertJsonCount(6, 'data');
    }

    public function test_novi_majstor_sa_mejlom_dobija_nalog_sa_ulogom(): void
    {
        $response = $this->kaoDispecer()
            ->postJson('/api/v1/admin/technicians', [
                'name' => 'Mirza Hadžić',
                'trade' => 'Vodoinstalater',
                'email' => 'mirza@haus.ba',
                'password' => 'haus1234',
            ])
            ->assertCreated()
            ->assertJsonPath('data.has_account', true)
            ->assertJsonPath('data.email', 'mirza@haus.ba');

        $technician = Technician::findOrFail($response->json('data.id'));
        $user = User::where('email', 'mirza@haus.ba')->firstOrFail();

        $this->assertSame($user->id, $technician->user_id);
        $this->assertTrue($user->hasRole('majstor'));

        // Novi majstor odmah moze u aplikaciju.
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'mirza@haus.ba',
            'password' => 'haus1234',
        ])->assertOk()->json('token');

        // Dispecer je do sada bio prijavljen u ovom testu, guard se resetuje.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/technician/jobs')->assertOk();
    }

    public function test_majstor_bez_mejla_ostaje_bez_naloga(): void
    {
        $this->kaoDispecer()
            ->postJson('/api/v1/admin/technicians', ['name' => 'Haris Bego', 'trade' => 'Bravar'])
            ->assertCreated()
            ->assertJsonPath('data.has_account', false)
            ->assertJsonPath('data.email', null);

        $this->assertSame(4, User::role('majstor')->count());
    }

    public function test_mejl_bez_lozinke_se_odbija(): void
    {
        $this->kaoDispecer()
            ->postJson('/api/v1/admin/technicians', [
                'name' => 'Haris Bego',
                'trade' => 'Bravar',
                'email' => 'haris@haus.ba',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('password');
    }

    public function test_majstor_se_iskljucuje_i_azurira(): void
    {
        $technician = Technician::where('name', 'Damir Hodžić')->firstOrFail();

        $this->kaoDispecer()
            ->patchJson('/api/v1/admin/technicians/'.$technician->id, ['active' => false, 'trade' => 'Vodoinstalater i grijanje'])
            ->assertOk()
            ->assertJsonPath('data.active', false)
            ->assertJsonPath('data.trade', 'Vodoinstalater i grijanje');

        $this->assertFalse($technician->refresh()->active);
    }

    public function test_majstor_sa_nalozima_se_ne_brise(): void
    {
        $technician = Technician::where('name', 'Damir Hodžić')->firstOrFail();
        $klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();

        Job::create([
            'number' => Job::nextNumber(),
            'user_id' => $klijent->id,
            'subscription_id' => $klijent->activeSubscription->id,
            'subscription_property_id' => $klijent->activeSubscription->properties->first()->id,
            'price_category_id' => PriceCategory::query()->value('id'),
            'technician_id' => $technician->id,
            'description' => 'Slavina curi u kupatilu.',
            'deadline_at' => now()->addDay(),
        ]);

        $this->kaoDispecer()
            ->deleteJson('/api/v1/admin/technicians/'.$technician->id)
            ->assertStatus(422);

        $this->assertNotNull(Technician::find($technician->id));
    }

    public function test_majstor_bez_naloga_se_brise_zajedno_sa_korisnickim_nalogom(): void
    {
        $technician = Technician::where('name', 'Senad Karić')->firstOrFail();
        $userId = $technician->user_id;

        $this->kaoDispecer()
            ->deleteJson('/api/v1/admin/technicians/'.$technician->id)
            ->assertOk();

        $this->assertNull(Technician::find($technician->id));
        $this->assertNull(User::find($userId));
    }
}
