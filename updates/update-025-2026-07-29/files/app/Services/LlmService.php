<?php

namespace App\Services;

use App\Services\Llm\AnthropicDriver;
use App\Services\Llm\GeminiDriver;
use App\Services\Llm\LlmDriver;
use App\Services\Llm\OllamaDriver;
use App\Services\Llm\OpenRouterDriver;
use Illuminate\Support\Facades\Log;

class LlmService
{
    private string $provider;

    private LlmDriver $chatDriver;

    private LlmDriver $embedDriver;

    public function __construct()
    {
        $this->provider = $provider = config('ai.provider');

        // Chat can move to any provider; embeddings must stay on one model —
        // stored chunk vectors are only comparable to vectors from the same
        // embedding model. Providers without embeddings fall back to openrouter.
        $embedProvider = config('ai.embed_provider') ?: match ($provider) {
            'gemini', 'anthropic' => 'openrouter',
            default => $provider,
        };

        $this->chatDriver = $this->makeDriver($provider);
        $this->embedDriver = $provider === $embedProvider
            ? $this->chatDriver
            : $this->makeDriver($embedProvider);
    }

    private function makeDriver(string $provider): LlmDriver
    {
        return match ($provider) {
            'openrouter' => new OpenRouterDriver(),
            'ollama' => new OllamaDriver(),
            'anthropic' => new AnthropicDriver(),
            'gemini' => new GeminiDriver(),
            default => throw new \RuntimeException("Unknown AI provider: {$provider}"),
        };
    }

    public function chat(string $system, string $user): string
    {
        try {
            return $this->chatDriver->chat($system, $user);
        } catch (\Throwable $e) {
            $fallback = $this->fallbackProvider();

            if (! $fallback) {
                throw $e;
            }

            Log::warning("LLM chat failed on {$this->provider}, retrying on {$fallback}: ".$e->getMessage());

            return $this->makeDriver($fallback)->chat($system, $user);
        }
    }

    /** The configured chat fallback, or null when there isn't a usable one. */
    private function fallbackProvider(): ?string
    {
        $fallback = config('ai.fallback');
        $cfg = config("ai.{$fallback}");

        if (! $fallback || $fallback === $this->provider || ! is_array($cfg)) {
            return null;
        }

        // Key-based providers are useless without a key.
        return array_key_exists('key', $cfg) && empty($cfg['key']) ? null : $fallback;
    }

    /** @return float[] */
    public function embed(string $text): array
    {
        return $this->embedDriver->embed($text);
    }

    /** @return float[][] */
    public function embedMany(array $texts): array
    {
        return $this->embedDriver->embedMany($texts);
    }

    /** Chat expecting a JSON object back, parsed tolerantly. */
    public function chatJson(string $system, string $user): array
    {
        return $this->parseJson($this->chat($system, $user));
    }

    /** Tolerant JSON extraction: strips code fences and leading/trailing prose. */
    private function parseJson(string $reply): array
    {
        $reply = preg_replace('/^```(?:json)?|```$/m', '', trim($reply));

        $start = strpos($reply, '{');
        $end = strrpos($reply, '}');

        if ($start === false || $end === false || $end < $start) {
            throw new \RuntimeException("LLM did not return JSON: {$reply}");
        }

        $candidate = substr($reply, $start, $end - $start + 1);

        $json = json_decode($candidate, true);

        // Models frequently emit raw newlines/tabs inside string values (illegal
        // JSON — "Control character error"). Escape control chars that sit inside
        // strings and retry once; this preserves the intended text (e.g. feedback
        // markdown line breaks) instead of discarding a good evaluation.
        if (! is_array($json)) {
            $json = json_decode($this->escapeControlChars($candidate), true);
        }

        if (! is_array($json)) {
            throw new \RuntimeException('LLM returned unparseable JSON ('.json_last_error_msg()."): {$reply}");
        }

        return $json;
    }

    /** Escape raw control characters that appear inside JSON string literals. */
    private function escapeControlChars(string $s): string
    {
        $out = '';
        $inStr = false;
        $esc = false;
        $len = strlen($s);
        for ($i = 0; $i < $len; $i++) {
            $c = $s[$i];
            if ($inStr) {
                if ($esc) { $out .= $c; $esc = false; continue; }
                if ($c === '\\') { $out .= $c; $esc = true; continue; }
                if ($c === '"') { $out .= $c; $inStr = false; continue; }
                if (ord($c) < 0x20) {
                    $out .= match ($c) {
                        "\n" => '\\n',
                        "\r" => '\\r',
                        "\t" => '\\t',
                        "\f" => '\\f',
                        "\x08" => '\\b',
                        default => sprintf('\\u%04x', ord($c)),
                    };
                    continue;
                }
                $out .= $c;
            } else {
                $out .= $c;
                if ($c === '"') { $inStr = true; }
            }
        }

        return $out;
    }
}
