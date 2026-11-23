<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

class FreeLlmApiDriver implements LlmDriver
{
    public function chat(string $system, string $user): string
    {
        $cfg = config('ai.freellmapi');

        $r = Http::withToken($cfg['key'])
            ->timeout(180)
            ->post(rtrim($cfg['base_url'], '/').'/chat/completions', [
                'model' => $cfg['chat_model'],
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
            ])
            ->throw()
            ->json();

        $content = trim($r['choices'][0]['message']['content'] ?? '');

        if ($content === '') {
            throw new \RuntimeException('FreeLLMAPI returned an empty reply.');
        }

        return $content;
    }

    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0];
    }

    public function embedMany(array $texts): array
    {
        $cfg = config('ai.freellmapi');

        $r = Http::withToken($cfg['key'])
            ->timeout(120)
            ->post(rtrim($cfg['base_url'], '/').'/embeddings', [
                'model' => 'text-embedding-3-small',
                'input' => array_values($texts),
            ])
            ->throw()
            ->json();

        $data = $r['data'];
        usort($data, fn ($a, $b) => $a['index'] <=> $b['index']);

        return array_column($data, 'embedding');
    }
}
