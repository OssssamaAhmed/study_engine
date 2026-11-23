<?php

namespace Tests\Unit;

use App\Services\LlmService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class JsonParseTest extends TestCase
{
    /** Call the private parseJson without booting Laravel (constructor needs config). */
    private function parse(string $reply): array
    {
        $svc = (new ReflectionClass(LlmService::class))->newInstanceWithoutConstructor();
        $m = new \ReflectionMethod(LlmService::class, 'parseJson');
        $m->setAccessible(true);

        return $m->invoke($svc, $reply);
    }

    public function test_plain_json_parses(): void
    {
        $this->assertSame(['a' => 1], $this->parse('{"a": 1}'));
    }

    public function test_code_fenced_json_parses(): void
    {
        $this->assertSame(['a' => 1], $this->parse("```json\n{\"a\": 1}\n```"));
    }

    public function test_raw_control_chars_in_strings_are_recovered(): void
    {
        // Illegal JSON: literal newline + tab inside a string value (what the LLM emits).
        $bad = "{\n  \"feedback_md\": \"line one\nline two\twith tab\",\n  \"n\": 5\n}";

        $out = $this->parse($bad);

        $this->assertSame(5, $out['n']);
        $this->assertStringContainsString("\n", $out['feedback_md']); // line break preserved
        $this->assertStringContainsString("\t", $out['feedback_md']); // tab preserved
    }
}
