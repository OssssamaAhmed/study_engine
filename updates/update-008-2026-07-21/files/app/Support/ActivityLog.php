<?php

namespace App\Support;

use App\Models\Attempt;
use App\Models\Exercise;
use App\Models\Transcript;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Days the learner used the app, one date per line in a flat file — the
 * streak counts ANY activity (browsing, chatting, submitting), not just
 * mission generation. ponytail: flat file beats a table for one list.
 */
final class ActivityLog
{
    private const PATH = 'activity-days.txt';

    /** Record today as an active day; the first run backfills from stored study data. */
    public static function touch(): void
    {
        $disk = Storage::disk('local');
        $fresh = ! $disk->exists(self::PATH);
        $days = $fresh ? self::backfill() : self::days();

        if ($fresh || ! $days->contains(today()->toDateString())) {
            $disk->put(self::PATH, $days->push(today()->toDateString())->unique()->sort()->implode("\n"));
        }
    }

    /** Consecutive active days counting back from today (an unvisited today doesn't break it). */
    public static function streak(): int
    {
        $days = self::days();

        $cursor = today();
        if (! $days->contains($cursor->toDateString())) {
            $cursor = $cursor->subDay();
        }

        $streak = 0;
        while ($days->contains($cursor->toDateString())) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        return $streak;
    }

    public static function clear(): void
    {
        Storage::disk('local')->delete(self::PATH);
    }

    private static function days(): Collection
    {
        $disk = Storage::disk('local');

        return $disk->exists(self::PATH)
            ? collect(explode("\n", trim($disk->get(self::PATH))))->filter()
            : collect();
    }

    /** Days already proven by stored data: mission dates, submissions, ingests. */
    private static function backfill(): Collection
    {
        return Exercise::pluck('date')
            ->concat(Attempt::pluck('created_at'))
            ->concat(Transcript::pluck('created_at'))
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->unique()->sort()->values();
    }
}
