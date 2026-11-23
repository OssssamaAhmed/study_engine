<?php

namespace App\Support;

use App\Models\Attempt;
use App\Models\Exercise;
use App\Models\Transcript;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Days the learner used the app, one date per line in a flat file per track
 * — the streak counts ANY activity (browsing, chatting, submitting), not
 * just mission generation. ponytail: flat file beats a table for one list.
 */
final class ActivityLog
{
    private const PATH_PREFIX = 'activity-days-';
    private const PATH_SUFFIX = '.txt';

    /** Record today as an active day; the first run backfills from stored study data. */
    public static function touch(?string $mode = null): void
    {
        $mode ??= StudyMode::current();
        $disk = Storage::disk('local');
        $path = self::path($mode);
        $fresh = ! $disk->exists($path);
        $days = $fresh ? self::backfill($mode) : self::days($mode);

        if ($fresh || ! $days->contains(today()->toDateString())) {
            $disk->put($path, $days->push(today()->toDateString())->unique()->sort()->implode("\n"));
        }
    }

    /** Consecutive active days counting back from today (an unvisited today doesn't break it). */
    public static function streak(?string $mode = null): int
    {
        $days = self::days($mode ?? StudyMode::current());

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

    public static function clear(?string $mode = null): void
    {
        Storage::disk('local')->delete(self::path($mode ?? StudyMode::current()));
    }

    private static function path(string $mode): string
    {
        return self::PATH_PREFIX.$mode.self::PATH_SUFFIX;
    }

    private static function days(string $mode): Collection
    {
        $disk = Storage::disk('local');
        $path = self::path($mode);

        return $disk->exists($path)
            ? collect(explode("\n", trim($disk->get($path))))->filter()
            : collect();
    }

    /**
     * Days already proven by stored data: mission dates, submissions,
     * ingests — all scoped to $mode via each model's global scope, which
     * follows StudyMode::current() at query time, so this must only run
     * while that mode is the active one (true for touch()'s normal callers:
     * the request middleware and the reset route, both of which read/set the
     * mode before calling in).
     */
    private static function backfill(string $mode): Collection
    {
        return Exercise::pluck('date')
            ->concat(Attempt::whereHas('exercise')->pluck('created_at'))
            ->concat(Transcript::pluck('created_at'))
            ->map(fn ($d) => substr((string) $d, 0, 10))
            ->unique()->sort()->values();
    }
}
