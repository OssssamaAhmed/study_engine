<?php

namespace App\Services\Llm;

interface LlmDriver
{
    public function chat(string $system, string $user): string;

    /** @return float[] */
    public function embed(string $text): array;

    /** @return float[][] one vector per input text, same order */
    public function embedMany(array $texts): array;
}
