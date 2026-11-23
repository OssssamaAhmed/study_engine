<?php

namespace App\Http\Middleware;

use App\Support\ActivityLog;
use App\Support\StudyMode;
use Closure;
use Illuminate\Http\Request;

/** Any page visit marks today as an active day for the active track's streak. */
class RecordActivity
{
    public function handle(Request $request, Closure $next)
    {
        ActivityLog::touch(StudyMode::current());

        return $next($request);
    }
}
