<?php

namespace Tests\Feature\Api;

use App\Enums\SubscriptionStatus;
use App\Models\Device;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    public function test_prijava_sa_ispravnim_kredencijalima_vraca_token_i_ulogu(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'klijent@haus.ba',
            'password' => 'haus1234',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'notif_push', 'notif_email', 'notif_marketing'],
                'token',
                'role',
            ])
            ->assertJsonPath('role', 'klijent')
            ->assertJsonPath('user.email', 'klijent@haus.ba');

        $this->assertNotEmpty($response->json('token'));
        $this->assertSame(1, PersonalAccessToken::count());
    }

    public function test_prijava_dispecera_vraca_ulogu_dispecer(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'dispecer@haus.ba',
            'password' => 'haus1234',
        ])->assertOk()->assertJsonPath('role', 'dispecer');
    }

    public function test_prijava_sa_pogresnom_lozinkom_vraca_422_na_bosanskom(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'klijent@haus.ba',
            'password' => 'pogresno',
        ]);

        $response->assertStatus(422)->assertJsonValidationErrors('email');

        $this->assertSame('Pogrešna mejl adresa ili lozinka.', $response->json('errors.email.0'));
        $this->assertSame(0, PersonalAccessToken::count());
    }

    public function test_prijava_sa_nepostojecim_mejlom_vraca_422(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'email' => 'niko@haus.ba',
            'password' => 'haus1234',
        ])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_prijava_bez_polja_vraca_422_sa_bosanskim_porukama(): void
    {
        $response = $this->postJson('/api/v1/auth/login', []);

        $response->assertStatus(422);

        $this->assertSame('Unesite mejl adresu.', $response->json('errors.email.0'));
        $this->assertSame('Unesite lozinku.', $response->json('errors.password.0'));
    }

    public function test_odjava_gasi_trenutni_token(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'klijent@haus.ba',
            'password' => 'haus1234',
        ])->json('token');

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();

        $this->assertSame(0, PersonalAccessToken::count());

        // Guard u testu kesira korisnika, pa ga oslobadjamo prije provjere.
        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/v1/me')->assertStatus(401);
    }

    public function test_me_bez_tokena_vraca_401_na_bosanskom(): void
    {
        $this->getJson('/api/v1/me')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Niste prijavljeni.');
    }

    public function test_me_sa_aktivnom_pretplatom_vraca_sazetak(): void
    {
        $user = User::where('email', 'klijent@haus.ba')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email', 'notif_push', 'notif_email', 'notif_marketing'],
                'role',
                'subscription' => [
                    'id', 'status', 'package' => ['id', 'name', 'slug'],
                    'starts_at', 'ends_at', 'remaining_visits', 'remaining_inspections',
                    'free_interventions', 'properties_count',
                ],
            ])
            ->assertJsonPath('role', 'klijent')
            ->assertJsonPath('subscription.package.slug', 'haus-plus')
            ->assertJsonPath('subscription.status', 'aktivna')
            ->assertJsonPath('subscription.remaining_visits', 3)
            ->assertJsonPath('subscription.free_interventions', 0)
            ->assertJsonPath('subscription.properties_count', 1);
    }

    public function test_me_zbraja_izlaske_preko_svih_stanova(): void
    {
        $user = User::where('email', 'pro@haus.ba')->firstOrFail();

        // Pro seed: 3 stana po 5 izlazaka i 2 pregleda.
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('subscription.package.slug', 'haus-pro')
            ->assertJsonPath('subscription.remaining_visits', 15)
            ->assertJsonPath('subscription.remaining_inspections', 6)
            ->assertJsonPath('subscription.properties_count', 3);
    }

    public function test_me_bez_pretplate_vraca_null(): void
    {
        $user = User::where('email', 'dispecer@haus.ba')->firstOrFail();

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('role', 'dispecer')
            ->assertJsonPath('subscription', null);
    }

    public function test_me_ne_racuna_pretplatu_koja_ceka_uplatu(): void
    {
        $user = User::where('email', 'klijent@haus.ba')->firstOrFail();
        $user->activeSubscription->update(['status' => SubscriptionStatus::CekanjeUplate]);

        $this->actingAs($user, 'sanctum')->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('subscription', null);
    }

    public function test_upis_uredjaja_radi_i_ne_duplira_isti_token(): void
    {
        $user = User::where('email', 'klijent@haus.ba')->firstOrFail();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', ['fcm_token' => 'abc123', 'platform' => 'ios'])
            ->assertOk()
            ->assertJsonPath('data.platform', 'ios');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', ['fcm_token' => 'abc123', 'platform' => 'android'])
            ->assertOk();

        $this->assertSame(1, Device::count());
        $this->assertSame('android', Device::first()->platform->value);
    }

    public function test_upis_uredjaja_trazi_prijavu(): void
    {
        $this->postJson('/api/v1/devices', ['fcm_token' => 'abc123', 'platform' => 'ios'])
            ->assertStatus(401);
    }

    public function test_upis_uredjaja_odbija_nepoznatu_platformu(): void
    {
        $user = User::where('email', 'klijent@haus.ba')->firstOrFail();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/v1/devices', ['fcm_token' => 'abc123', 'platform' => 'windows']);

        $response->assertStatus(422);

        $this->assertSame('Platforma može biti ios ili android.', $response->json('errors.platform.0'));
    }
}
