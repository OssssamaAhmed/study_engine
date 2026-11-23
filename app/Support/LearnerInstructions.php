<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * The learner's persistent free-text instructions ("personal context"),
 * injected into every mission generation and tutor chat.
 * ponytail: a flat file beats a settings table for one single-user value.
 */
final class LearnerInstructions
{
    private const PATH = 'learner-instructions.md';

    public static function get(): string
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::PATH) ? trim($disk->get(self::PATH)) : '';
    }

    public static function set(string $text): void
    {
        Storage::disk('local')->put(self::PATH, trim($text));
    }
}
