<?php

namespace Tests\Feature\Services;

use App\Exceptions\SupportChatException;
use App\Services\SupportChatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupportChatGeminiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();

        config()->set('services.support_ai.provider', 'gemini');
        config()->set('services.gemini.api_key', 'test-gemini-key');
        config()->set('services.gemini.model', 'gemini-2.5-flash');
    }

    public function test_gemini_provider_vraca_odgovor(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['role' => 'model', 'parts' => [['text' => 'HAUS Plus košta 169 KM godišnje.']]],
                ]],
                'usageMetadata' => ['promptTokenCount' => 1300, 'candidatesTokenCount' => 35],
            ]),
        ]);

        $service = app(SupportChatService::class);
        $reply = $service->chat([
            ['role' => 'user', 'content' => 'Pozdrav, imam pitanje.'],
            ['role' => 'assistant', 'content' => 'Izvolite, kako mogu pomoći?'],
            ['role' => 'user', 'content' => 'Koliko košta HAUS Plus?'],
        ]);

        $this->assertSame('HAUS Plus košta 169 KM godišnje.', $reply);
        $this->assertSame(['input_tokens' => 1300, 'output_tokens' => 35], $service->zadnjaPotrosnja());

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-2.5-flash:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-gemini-key')
                && str_contains($request['system_instruction']['parts'][0]['text'], 'HAUS Plus')
                && $request['contents'][0]['role'] === 'user'
                && $request['contents'][1]['role'] === 'model'
                && $request['contents'][2]['role'] === 'user';
        });
    }

    public function test_gemini_bez_kljuca_baca_nije_podesen(): void
    {
        config()->set('services.gemini.api_key', '');

        $this->expectException(SupportChatException::class);

        app(SupportChatService::class)->chat([['role' => 'user', 'content' => 'Test poruka podrške.']]);
    }

    public function test_gemini_429_se_mapira_u_domensku_gresku(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['status' => 'RESOURCE_EXHAUSTED']], 429)]);

        $this->expectException(SupportChatException::class);

        app(SupportChatService::class)->chat([['role' => 'user', 'content' => 'Test poruka podrške.']]);
    }

    public function test_gemini_prazan_odgovor_baca_gresku(): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['role' => 'model', 'parts' => []]]],
            ]),
        ]);

        $this->expectException(SupportChatException::class);

        app(SupportChatService::class)->chat([['role' => 'user', 'content' => 'Test poruka podrške.']]);
    }
}
