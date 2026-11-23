<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/** The learner's display name for the dashboard greeting. Same flat-file pattern as LearnerInstructions. */
final class LearnerName
{
    private const PATH = 'learner-name.txt';

    public static function get(): string
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::PATH) ? trim($disk->get(self::PATH)) : '';
    }

    public static function set(string $name): void
    {
        Storage::disk('local')->put(self::PATH, trim($name));
    }
}
