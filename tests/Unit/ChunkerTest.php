<?php

namespace Tests\Unit;

use App\Services\IngestService;
use App\Services\LlmService;
use PHPUnit\Framework\TestCase;

class ChunkerTest extends TestCase
{
    private function chunker(): IngestService
    {
        $stub = $this->createStub(LlmService::class);

        return new IngestService($stub);
    }

    public function test_short_text_is_one_chunk(): void
    {
        $this->assertSame(['hello world'], $this->chunker()->chunk('hello world'));
    }

    public function test_empty_text_gives_no_chunks(): void
    {
        $this->assertSame([], $this->chunker()->chunk('   '));
    }

    public function test_long_text_chunks_with_overlap(): void
    {
        $text = str_repeat('abcdefghij', 500); // 5000 chars, no whitespace to trim

        $chunks = $this->chunker()->chunk($text);

        $this->assertCount(3, $chunks); // starts at 0, 1800, 3600
        $this->assertSame(mb_substr($text, 0, 2000), $chunks[0]);
        $this->assertSame(mb_substr($text, 1800, 2000), $chunks[1]);
        $this->assertSame(mb_substr($text, 3600, 2000), $chunks[2]);
        // 200-char overlap between consecutive chunks
        $this->assertSame(mb_substr($chunks[0], -200), mb_substr($chunks[1], 0, 200));
    }
}
