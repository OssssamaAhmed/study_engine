<?php

namespace Tests\Feature;

use App\Services\LlmService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiDriverTest extends TestCase
{
    public function test_gemini_chat_sends_correct_shape_and_parses_reply(): void
    {
        config(['ai.provider' => 'gemini', 'ai.gemini.key' => 'test-key', 'ai.gemini.model' => 'gemini-2.5-pro']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [
                        ['text' => 'thinking...', 'thought' => true],
                        ['text' => 'pong'],
                    ]],
                    'finishReason' => 'STOP',
                ]],
            ]),
        ]);

        $reply = app(LlmService::class)->chat('system prompt', 'ping');

        $this->assertSame('pong', $reply); // thought part filtered out

        Http::assertSent(function ($request) {
            return str_contains($request->url(), 'models/gemini-2.5-pro:generateContent')
                && $request->hasHeader('x-goog-api-key', 'test-key')
                && $request['system_instruction']['parts'][0]['text'] === 'system prompt'
                && $request['contents'][0]['parts'][0]['text'] === 'ping';
        });
    }

    public function test_gemini_provider_routes_embeddings_to_openrouter_by_default(): void
    {
        config(['ai.provider' => 'gemini', 'ai.embed_provider' => null, 'ai.gemini.key' => 'test-key']);

        Http::fake([
            'openrouter.ai/*' => Http::response([
                'data' => [['index' => 0, 'embedding' => [0.1, 0.2]]],
            ]),
        ]);

        $vector = app(LlmService::class)->embed('hello');

        $this->assertSame([0.1, 0.2], $vector);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'openrouter.ai/api/v1/embeddings'));
    }

    public function test_explicit_embed_provider_gemini_throws_clear_error(): void
    {
        config(['ai.provider' => 'gemini', 'ai.embed_provider' => 'gemini', 'ai.gemini.key' => 'test-key']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI_EMBED_PROVIDER');

        app(LlmService::class)->embed('hello');
    }

    public function test_overloaded_gemini_falls_back_to_openrouter(): void
    {
        config([
            'ai.provider' => 'gemini',
            'ai.gemini.key' => 'test-key',
            'ai.fallback' => 'openrouter',
            'ai.openrouter.key' => 'or-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response(
                ['error' => ['code' => 503, 'message' => 'high demand']], 503
            ),
            'openrouter.ai/*' => Http::response([
                'choices' => [['message' => ['content' => 'rescued']]],
            ]),
        ]);

        $this->assertSame('rescued', app(LlmService::class)->chat('s', 'u'));
    }

    public function test_no_fallback_configured_rethrows(): void
    {
        config(['ai.provider' => 'gemini', 'ai.gemini.key' => 'test-key', 'ai.fallback' => null]);

        Http::fake(['generativelanguage.googleapis.com/*' => Http::response([], 503)]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('overloaded');

        app(LlmService::class)->chat('s', 'u');
    }

    public function test_empty_gemini_reply_throws(): void
    {
        config(['ai.provider' => 'gemini', 'ai.gemini.key' => 'test-key']);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => []], 'finishReason' => 'MAX_TOKENS']],
            ]),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('MAX_TOKENS');

        app(LlmService::class)->chat('s', 'u');
    }
}
