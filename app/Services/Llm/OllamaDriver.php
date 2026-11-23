<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

class OllamaDriver implements LlmDriver
{
    public function chat(string $system, string $user): string
    {
        $cfg = config('ai.ollama');

        $r = Http::timeout(300)
            ->post($cfg['base_url'].'/api/chat', [
                'model' => $cfg['chat_model'],
                'stream' => false,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ])
            ->throw()
            ->json();

        return trim($r['message']['content'] ?? '');
    }

    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0];
    }

    public function embedMany(array $texts): array
    {
        $cfg = config('ai.ollama');

        $r = Http::timeout(120)
            ->post($cfg['base_url'].'/api/embed', [
                'model' => $cfg['embed_model'],
                'input' => array_values($texts),
            ])
            ->throw()
            ->json();

        return $r['embeddings'];
    }
}
