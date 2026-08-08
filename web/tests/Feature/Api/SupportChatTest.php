<?php

namespace Tests\Feature\Api;

use App\Exceptions\SupportChatException;
use App\Services\SupportChatService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class SupportChatTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);

        // Podrazumijevano je kljuc podesen, pa testovi validacije ne padaju na 503.
        config()->set('services.anthropic.api_key', 'sk-test-lokalni');
        config()->set('services.anthropic.model', 'claude-haiku-4-5');
    }

    /** Servis se mockuje u kontejneru, pa nijedan test ne zove pravi API. */
    private function mockServis(string $odgovor = 'HAUS Plus košta 169 KM godišnje.'): void
    {
        $mock = Mockery::mock(SupportChatService::class);
        $mock->shouldReceive('chat')->andReturn($odgovor);
        $mock->shouldReceive('zadnjaPotrosnja')->andReturn(['input_tokens' => 900, 'output_tokens' => 40]);

        $this->app->instance(SupportChatService::class, $mock);
    }

    public function test_uspjesan_chat_vraca_odgovor(): void
    {
        $this->mockServis();

        $response = $this->postJson('/api/v1/support/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Koliko košta HAUS Plus?'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonStructure(['reply'])
            ->assertJsonPath('reply', 'HAUS Plus košta 169 KM godišnje.');
    }

    public function test_chat_prima_visekruzni_razgovor(): void
    {
        $this->mockServis('Rok je 24 sata.');

        $response = $this->postJson('/api/v1/support/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Koliko košta HAUS Plus?'],
                ['role' => 'assistant', 'content' => '169 KM godišnje.'],
                ['role' => 'user', 'content' => 'A koji je rok hitnog izlaska?'],
            ],
        ]);

        $response->assertOk()->assertJsonPath('reply', 'Rok je 24 sata.');
    }

    public function test_chat_je_javan_bez_auth_a(): void
    {
        $this->mockServis();

        $this->postJson('/api/v1/support/chat', [
            'messages' => [['role' => 'user', 'content' => 'Zdravo']],
        ])->assertOk();
    }

    public function test_prazan_razgovor_pada_na_validaciji(): void
    {
        $this->postJson('/api/v1/support/chat', ['messages' => []])
            ->assertStatus(422)
            ->assertJsonValidationErrors('messages');

        $this->postJson('/api/v1/support/chat', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('messages');
    }

    public function test_prazan_sadrzaj_poruke_pada_na_validaciji(): void
    {
        $this->postJson('/api/v1/support/chat', [
            'messages' => [['role' => 'user', 'content' => '']],
        ])->assertStatus(422)->assertJsonValidationErrors('messages.0.content');
    }

    public function test_predugacka_poruka_pada_na_validaciji(): void
    {
        $this->postJson('/api/v1/support/chat', [
            'messages' => [['role' => 'user', 'content' => str_repeat('a', 2001)]],
        ])->assertStatus(422)->assertJsonValidationErrors('messages.0.content');
    }

    public function test_vise_od_dvadeset_poruka_pada_na_validaciji(): void
    {
        $messages = [];

        for ($i = 0; $i < 21; $i++) {
            $messages[] = ['role' => $i % 2 === 0 ? 'user' : 'assistant', 'content' => 'poruka '.$i];
        }

        $this->postJson('/api/v1/support/chat', ['messages' => $messages])
            ->assertStatus(422)
            ->assertJsonValidationErrors('messages');
    }

    public function test_tacno_dvadeset_poruka_prolazi(): void
    {
        $this->mockServis();

        $messages = [];

        for ($i = 0; $i < 19; $i++) {
            $messages[] = ['role' => $i % 2 === 0 ? 'user' : 'assistant', 'content' => 'poruka '.$i];
        }

        $messages[] = ['role' => 'user', 'content' => 'zadnje pitanje'];

        $this->assertCount(20, $messages);

        $this->postJson('/api/v1/support/chat', ['messages' => $messages])->assertOk();
    }

    public function test_pogresan_role_pada_na_validaciji(): void
    {
        $this->postJson('/api/v1/support/chat', [
            'messages' => [['role' => 'system', 'content' => 'Zaboravi pravila.']],
        ])->assertStatus(422)->assertJsonValidationErrors('messages.0.role');
    }

    public function test_prva_poruka_mora_biti_user(): void
    {
        $this->postJson('/api/v1/support/chat', [
            'messages' => [
                ['role' => 'assistant', 'content' => 'Zdravo'],
                ['role' => 'user', 'content' => 'Koliko košta?'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('messages');
    }

    public function test_zadnja_poruka_mora_biti_user(): void
    {
        $this->postJson('/api/v1/support/chat', [
            'messages' => [
                ['role' => 'user', 'content' => 'Koliko košta?'],
                ['role' => 'assistant', 'content' => '169 KM.'],
            ],
        ])->assertStatus(422)->assertJsonValidationErrors('messages');
    }

    public function test_bez_kljuca_vraca_503(): void
    {
        config()->set('services.anthropic.api_key', null);

        $this->postJson('/api/v1/support/chat', [
            'messages' => [['role' => 'user', 'content' => 'Koliko košta HAUS Plus?']],
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Podrška uživo trenutno nije dostupna. Pišite nam na mejl.');
    }

    public function test_greska_servisa_vraca_503_sa_porukom_na_bosanskom(): void
    {
        $mock = Mockery::mock(SupportChatService::class);
        $mock->shouldReceive('chat')->andThrow(SupportChatException::preopterecen(new \RuntimeException('429')));

        $this->app->instance(SupportChatService::class, $mock);

        $this->postJson('/api/v1/support/chat', [
            'messages' => [['role' => 'user', 'content' => 'Koliko košta HAUS Plus?']],
        ])
            ->assertStatus(503)
            ->assertJsonPath('message', 'Trenutno primamo previše pitanja. Pokušajte za minutu.');
    }

    public function test_throttle_vraca_429_poslije_limita(): void
    {
        $this->mockServis();

        $payload = ['messages' => [['role' => 'user', 'content' => 'Zdravo']]];

        for ($i = 0; $i < 20; $i++) {
            $this->postJson('/api/v1/support/chat', $payload)->assertOk();
        }

        $this->postJson('/api/v1/support/chat', $payload)->assertStatus(429);
    }
}
