<?php

namespace Tests\Feature\Services;

use App\Exceptions\SupportChatException;
use App\Services\SupportChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupportChatOpenAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        config()->set('services.support_ai.provider', 'openai');
        config()->set('services.openai.api_key', 'sk-test');
        config()->set('services.openai.model', 'gpt-4o-mini');
    }

    public function test_openai_provider_vraca_odgovor(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => 'HAUS Plus košta 169 KM godišnje.']]],
                'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 40],
            ]),
        ]);

        $service = app(SupportChatService::class);
        $reply = $service->chat([['role' => 'user', 'content' => 'Koliko košta HAUS Plus?']]);

        $this->assertSame('HAUS Plus košta 169 KM godišnje.', $reply);
        $this->assertSame(['input_tokens' => 1200, 'output_tokens' => 40], $service->zadnjaPotrosnja());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'api.openai.com/v1/chat/completions')
                && $request['model'] === 'gpt-4o-mini'
                && $request['messages'][0]['role'] === 'system'
                && str_contains($request['messages'][0]['content'], 'HAUS Plus')
                && $request['messages'][1]['role'] === 'user';
        });
    }

    public function test_openai_bez_kljuca_baca_nije_podesen(): void
    {
        config()->set('services.openai.api_key', '');

        $this->expectException(SupportChatException::class);

        app(SupportChatService::class)->chat([['role' => 'user', 'content' => 'Test poruka podrške.']]);
    }

    public function test_openai_429_se_mapira_u_domensku_gresku(): void
    {
        Http::fake(['api.openai.com/*' => Http::response(['error' => ['message' => 'rate limited']], 429)]);

        $this->expectException(SupportChatException::class);

        app(SupportChatService::class)->chat([['role' => 'user', 'content' => 'Test poruka podrške.']]);
    }

    public function test_openai_prazan_odgovor_baca_gresku(): void
    {
        Http::fake([
            'api.openai.com/*' => Http::response([
                'choices' => [['message' => ['role' => 'assistant', 'content' => '']]],
                'usage' => ['prompt_tokens' => 10, 'completion_tokens' => 0],
            ]),
        ]);

        $this->expectException(SupportChatException::class);

        app(SupportChatService::class)->chat([['role' => 'user', 'content' => 'Test poruka podrške.']]);
    }
}
