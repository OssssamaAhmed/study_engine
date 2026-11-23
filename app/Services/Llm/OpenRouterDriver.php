<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

class OpenRouterDriver implements LlmDriver
{
    public function chat(string $system, string $user): string
    {
        $cfg = config('ai.openrouter');

        // The free tier occasionally returns reasoning with an empty final
        // content, or an error body under HTTP 200 — retry once before failing.
        foreach ([1, 2] as $attempt) {
            $r = Http::withToken($cfg['key'])
                // Nemotron Ultra free tier can be slow; give it room.
                ->timeout(300)
                ->post($cfg['base_url'].'/chat/completions', [
                    'model' => $cfg['chat_model'],
                    'messages' => [
                        ['role' => 'system', 'content' => $system],
                        ['role' => 'user', 'content' => $user],
                    ],
                ])
                ->throw()
                ->json();

            if (isset($r['error'])) {
                $lastError = json_encode($r['error']);
                continue;
            }

            // Reasoning models may inline a <think> block before the answer.
            $content = trim(preg_replace('/<think>.*?<\/think>/s', '', $r['choices'][0]['message']['content'] ?? ''));

            if ($content !== '') {
                return $content;
            }

            $lastError = 'empty completion (finish_reason: '.($r['choices'][0]['finish_reason'] ?? '?').')';
        }

        throw new \RuntimeException("OpenRouter chat failed after retry: {$lastError}");
    }

    public function embed(string $text): array
    {
        return $this->embedMany([$text])[0];
    }

    public function embedMany(array $texts): array
    {
        $cfg = config('ai.openrouter');

        $r = Http::withToken($cfg['key'])
            ->timeout(120)
            ->post($cfg['base_url'].'/embeddings', [
                'model' => $cfg['embed_model'],
                'input' => array_values($texts),
            ])
            ->throw()
            ->json();

        $data = $r['data'];
        usort($data, fn ($a, $b) => $a['index'] <=> $b['index']);

        return array_column($data, 'embedding');
    }
}
