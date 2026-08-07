<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
    }

    private function korisnik(string $email): User
    {
        return User::where('email', $email)->firstOrFail();
    }

    public function test_klijentska_grupa_bez_prijave_vraca_401(): void
    {
        $this->getJson('/api/v1/client/ping')
            ->assertStatus(401)
            ->assertJsonPath('message', 'Niste prijavljeni.');
    }

    public function test_admin_grupa_bez_prijave_vraca_401(): void
    {
        $this->getJson('/api/v1/admin/ping')->assertStatus(401);
    }

    public function test_klijent_ulazi_u_klijentsku_grupu(): void
    {
        $this->actingAs($this->korisnik('klijent@haus.ba'), 'sanctum')
            ->getJson('/api/v1/client/ping')
            ->assertOk()
            ->assertJsonPath('data.scope', 'klijent');
    }

    public function test_klijent_ne_ulazi_u_admin_grupu(): void
    {
        $this->actingAs($this->korisnik('klijent@haus.ba'), 'sanctum')
            ->getJson('/api/v1/admin/ping')
            ->assertStatus(403)
            ->assertJsonPath('message', 'Nemate pristup ovom dijelu aplikacije.');
    }

    public function test_dispecer_ulazi_u_admin_grupu(): void
    {
        $this->actingAs($this->korisnik('dispecer@haus.ba'), 'sanctum')
            ->getJson('/api/v1/admin/ping')
            ->assertOk()
            ->assertJsonPath('data.scope', 'dispecer');
    }

    public function test_dispecer_ne_ulazi_u_klijentsku_grupu(): void
    {
        $this->actingAs($this->korisnik('dispecer@haus.ba'), 'sanctum')
            ->getJson('/api/v1/client/ping')
            ->assertStatus(403);
    }

    public function test_guard_radi_i_sa_bearer_tokenom(): void
    {
        $token = $this->postJson('/api/v1/auth/login', [
            'email' => 'klijent@haus.ba',
            'password' => 'haus1234',
        ])->json('token');

        $this->withToken($token)->getJson('/api/v1/client/ping')->assertOk();
        $this->withToken($token)->getJson('/api/v1/admin/ping')->assertStatus(403);
    }

    public function test_javni_endpointi_ostaju_otvoreni(): void
    {
        foreach (['/api/v1/packages', '/api/v1/cities', '/api/v1/settings/public'] as $url) {
            $this->getJson($url)->assertOk();
        }
    }
}
