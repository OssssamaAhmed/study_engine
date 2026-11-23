<?php

namespace App\Services\Llm;

use Illuminate\Support\Facades\Http;

class GeminiDriver implements LlmDriver
{
    public function chat(string $system, string $user): string
    {
        $cfg = config('ai.gemini');

        // Short ladder on purpose: LlmService falls back to another provider
        // afterwards, and a browser request shouldn't sit here for a minute.
        $maxAttempts = 3;
        $waitSeconds = 2;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = Http::withHeaders(['x-goog-api-key' => $cfg['key']])
                ->timeout(300)
                ->post("https://generativelanguage.googleapis.com/v1beta/models/{$cfg['model']}:generateContent", [
                    'system_instruction' => ['parts' => [['text' => $system]]],
                    'contents' => [
                        ['role' => 'user', 'parts' => [['text' => $user]]],
                    ],
                ]);

            // Retry on 503 (overloaded) or 429 (rate limit) with exponential backoff.
            // On the last attempt fall out of the loop so the message below is the
            // one that surfaces — not a raw RequestException from throw().
            if (in_array($response->status(), [429, 503])) {
                if ($attempt === $maxAttempts) {
                    break;
                }

                sleep($waitSeconds);
                $waitSeconds *= 2;
                continue;
            }

            $response->throw();
            $r = $response->json();

            $parts = $r['candidates'][0]['content']['parts'] ?? [];
            // Thinking models may emit thought parts; keep only the answer text.
            $text = collect($parts)
                ->reject(fn ($p) => ($p['thought'] ?? false) === true)
                ->pluck('text')
                ->implode('');

            if (trim($text) === '') {
                throw new \RuntimeException(
                    'Gemini returned no text (finishReason: '.($r['candidates'][0]['finishReason'] ?? '?').')'
                );
            }

            return trim($text);
        }

        throw new \RuntimeException(
            'Gemini is overloaded or rate limited (HTTP '.$response->status().') after '.$maxAttempts.' attempts.'
        );
    }

    public function embed(string $text): array
    {
        throw new \RuntimeException(
            'Embeddings stay on the original provider so stored vectors remain comparable — set AI_EMBED_PROVIDER=openrouter (or ollama).'
        );
    }

    public function embedMany(array $texts): array
    {
        throw new \RuntimeException(
            'Embeddings stay on the original provider so stored vectors remain comparable — set AI_EMBED_PROVIDER=openrouter (or ollama).'
        );
    }
}
