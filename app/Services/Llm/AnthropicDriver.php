<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

class AnthropicDriver implements LlmDriver
{
    public function chat(string $system, string $user): string
    {
        $cfg = config('ai.anthropic');

        $r = Http::withHeaders([
                'x-api-key' => $cfg['key'],
                'anthropic-version' => '2023-06-01',
            ])
            ->timeout(300)
            ->post('https://api.anthropic.com/v1/messages', [
                'model' => $cfg['model'],
                'max_tokens' => 4096,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $user],
                ],
            ])
            ->throw()
            ->json();

        return trim($r['content'][0]['text'] ?? '');
    }

    public function embed(string $text): array
    {
        throw new \RuntimeException(
            'Anthropic has no embeddings API - set AI_EMBED_PROVIDER=openrouter (or ollama).'
        );
    }

    public function embedMany(array $texts): array
    {
        throw new \RuntimeException(
            'Anthropic has no embeddings API - set AI_EMBED_PROVIDER=openrouter (or ollama).'
        );
    }
}


