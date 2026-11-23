<?php

namespace App\Console\Commands;

use App\Services\LlmService;
use Illuminate\Console\Command;

class LlmTest extends Command
{
    protected $signature = 'llm:test';

    protected $description = 'Smoke-test chat + embeddings against the active AI provider';

    public function handle(LlmService $llm): int
    {
        $this->info('Provider: '.config('ai.provider'));

        $reply = $llm->chat('You are a smoke test. Answer tersely.', 'Reply with the single word: pong');
        $this->line("Chat reply: {$reply}");

        $vec = $llm->embed('spaced repetition smoke test');
        $preview = implode(', ', array_map(fn ($v) => round($v, 4), array_slice($vec, 0, 3)));
        $this->line('Embedding: '.count($vec)." dims, first 3: [{$preview}]");

        $this->info('LLM smoke test passed.');

        return self::SUCCESS;
    }
}
