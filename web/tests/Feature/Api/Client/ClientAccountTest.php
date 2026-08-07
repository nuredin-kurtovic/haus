<?php

namespace Tests\Feature\Api\Client;

use App\Enums\HomeRecordType;
use App\Mail\PromjenaAdreseMail;
use App\Models\HomeRecord;
use App\Models\Subscription;
use App\Models\SubscriptionProperty;
use App\Models\User;
use App\Services\SettingsService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ClientAccountTest extends TestCase
{
    use RefreshDatabase;

    private User $klijent;

    private User $pro;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        Mail::fake();

        $this->klijent = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $this->pro = User::where('email', 'pro@haus.ba')->firstOrFail();
    }

    public function test_pretplata_nosi_paket_prava_po_adresi_i_historiju(): void
    {
        $response = $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/subscription')
            ->assertOk()
            ->assertJsonPath('package.slug', 'haus-plus')
            ->assertJsonPath('package.labor_discount_pct', 25)
            ->assertJsonPath('package.warranty_months', 12)
            ->assertJsonPath('status', 'aktivna')
            ->assertJsonPath('auto_renew', true)
            ->assertJsonCount(1, 'properties')
            ->assertJsonPath('properties.0.city', 'Sarajevo')
            ->assertJsonPath('properties.0.street', 'Grbavička 12/3')
            ->assertJsonPath('properties.0.use', 'zivim')
            ->assertJsonPath('properties.0.remaining_visits', 3)
            ->assertJsonPath('properties.0.remaining_inspections', 1)
            ->assertJsonCount(0, 'payments');

        $this->assertNotNull($response->json('starts_at'));
        $this->assertNotNull($response->json('ends_at'));
        $this->assertEqualsWithDelta(169, (float) $response->json('price_paid'), 0.001);
    }

    public function test_pro_vidi_sve_svoje_stanove(): void
    {
        $this->actingAs($this->pro, 'sanctum')
            ->getJson('/api/v1/client/subscription')
            ->assertOk()
            ->assertJsonPath('package.slug', 'haus-pro')
            ->assertJsonCount(3, 'properties');
    }

    public function test_otkazivanje_gasi_automatsku_obnovu_i_idempotentno_je(): void
    {
        $prvi = $this->actingAs($this->klijent, 'sanctum')
            ->postJson('/api/v1/client/subscription/cancel')
            ->assertOk()
            ->assertJsonPath('auto_renew', false);

        $subscription = Subscription::where('user_id', $this->klijent->id)->firstOrFail();
        $this->assertFalse($subscription->auto_renew);

        $this->assertStringContainsString('Automatska obnova je isključena.', (string) $prvi->json('message'));
        $this->assertStringContainsString($subscription->ends_at->format('d.m.Y'), (string) $prvi->json('message'));

        $drugi = $this->actingAs($this->klijent, 'sanctum')
            ->postJson('/api/v1/client/subscription/cancel')
            ->assertOk()
            ->assertJsonPath('auto_renew', false);

        $this->assertSame($prvi->json('message'), $drugi->json('message'));
        $this->assertFalse(Subscription::where('user_id', $this->klijent->id)->firstOrFail()->auto_renew);
        // Pretplata i dalje vrijedi do isteka.
        $this->assertSame('aktivna', Subscription::where('user_id', $this->klijent->id)->firstOrFail()->status->value);
    }

    public function test_profil_vraca_ime_mejl_i_prekidace(): void
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/profile')
            ->assertOk()
            ->assertJsonPath('data.name', 'Amina Kovačević')
            ->assertJsonPath('data.email', 'klijent@haus.ba')
            ->assertJsonPath('data.notifications.push', true)
            ->assertJsonPath('data.notifications.email', true)
            ->assertJsonPath('data.notifications.marketing', false);
    }

    public function test_profil_mijenja_ime_i_prekidace(): void
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->putJson('/api/v1/client/profile', [
                'name' => 'Amina Kovačević Hodžić',
                'notifications' => ['push' => false, 'email' => true, 'marketing' => true],
            ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Amina Kovačević Hodžić')
            ->assertJsonPath('data.notifications.push', false)
            ->assertJsonPath('data.notifications.email', true)
            ->assertJsonPath('data.notifications.marketing', true);

        $this->klijent->refresh();

        $this->assertSame('Amina Kovačević Hodžić', $this->klijent->name);
        $this->assertFalse($this->klijent->notif_push);
        $this->assertTrue($this->klijent->notif_email);
        $this->assertTrue($this->klijent->notif_marketing);
    }

    public function test_profil_ne_mijenja_mejl(): void
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->putJson('/api/v1/client/profile', [
                'name' => 'Amina Kovačević',
                'email' => 'druga@haus.ba',
            ])
            ->assertOk()
            ->assertJsonPath('data.email', 'klijent@haus.ba');

        $this->assertSame('klijent@haus.ba', $this->klijent->refresh()->email);
    }

    public function test_profil_trazi_ime(): void
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->putJson('/api/v1/client/profile', ['name' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_zahtjev_za_promjenu_adrese_pise_karton_i_salje_mejl(): void
    {
        app(SettingsService::class)->set('dispecer_email', 'dispecer@haus.ba');

        $poruka = 'Selimo se na novu adresu, Titova 5, od prvog sljedećeg mjeseca.';

        $this->actingAs($this->klijent, 'sanctum')
            ->postJson('/api/v1/client/address-change-request', ['message' => $poruka])
            ->assertCreated()
            ->assertJsonPath('message', 'Zahtjev je poslan. Dispečer se javlja na vaš mejl.');

        $stan = SubscriptionProperty::query()
            ->whereIn('subscription_id', Subscription::where('user_id', $this->klijent->id)->pluck('id'))
            ->firstOrFail();

        $record = HomeRecord::where('subscription_property_id', $stan->id)->firstOrFail();

        $this->assertSame(HomeRecordType::Napomena, $record->type);
        $this->assertSame('Zahtjev za promjenu adrese', $record->title);
        $this->assertSame($poruka, $record->body);
        $this->assertNotNull($record->recorded_at);

        Mail::assertQueued(
            PromjenaAdreseMail::class,
            fn (PromjenaAdreseMail $mail) => $mail->hasTo('dispecer@haus.ba')
                && $mail->klijent->is($this->klijent)
        );
    }

    public function test_zahtjev_za_promjenu_adrese_trazi_poruku(): void
    {
        $this->actingAs($this->klijent, 'sanctum')
            ->postJson('/api/v1/client/address-change-request', ['message' => 'kratko'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');

        $this->assertSame(0, HomeRecord::count());
    }

    public function test_zahtjev_za_promjenu_adrese_odbija_tudji_stan(): void
    {
        $tudji = SubscriptionProperty::query()
            ->whereIn('subscription_id', Subscription::where('user_id', $this->pro->id)->pluck('id'))
            ->value('id');

        $this->actingAs($this->klijent, 'sanctum')
            ->postJson('/api/v1/client/address-change-request', [
                'message' => 'Selimo se na novu adresu u istom gradu.',
                'subscription_property_id' => $tudji,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('subscription_property_id');
    }

    public function test_klijentski_cjenovnik_dodaje_my_price(): void
    {
        $response = $this->actingAs($this->klijent, 'sanctum')
            ->getJson('/api/v1/client/price-list?q='.rawurlencode('Zamjena prekidača'))
            ->assertOk()
            ->assertJsonPath('meta.my_package.slug', 'haus-plus');

        $stavka = collect($response->json('data.0.items'))
            ->firstWhere('name', 'Zamjena prekidača ili utičnice');

        $this->assertNotNull($stavka);
        $this->assertEqualsWithDelta(35, (float) $stavka['base_price'], 0.001);
        // HAUS Plus ima 25 posto popusta na rad: 35 se zaokruzuje na 26.
        $this->assertSame(26, $stavka['my_price']);
        $this->assertSame(26, $stavka['prices']['plus']);
    }

    public function test_javni_cjenovnik_nema_my_price(): void
    {
        $response = $this->getJson('/api/v1/price-list?q='.rawurlencode('Zamjena prekidača'))->assertOk();

        $stavka = collect($response->json('data.0.items'))->first();

        $this->assertArrayNotHasKey('my_price', $stavka);
        $this->assertArrayHasKey('prices', $stavka);
    }

    public function test_pro_cjenovnik_ima_svoj_my_price(): void
    {
        $response = $this->actingAs($this->pro, 'sanctum')
            ->getJson('/api/v1/client/price-list?q='.rawurlencode('Zamjena prekidača'))
            ->assertOk()
            ->assertJsonPath('meta.my_package.slug', 'haus-pro');

        $stavka = collect($response->json('data.0.items'))
            ->firstWhere('name', 'Zamjena prekidača ili utičnice');

        $this->assertSame(26, $stavka['my_price']);
    }
}
