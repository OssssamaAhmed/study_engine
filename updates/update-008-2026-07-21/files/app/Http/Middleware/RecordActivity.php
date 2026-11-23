<?php

namespace App\Http\Middleware;

use App\Support\ActivityLog;
use Closure;
use Illuminate\Http\Request;

/** Any page visit marks today as an active day for the streak. */
class RecordActivity
{
    public function handle(Request $request, Closure $next)
    {
        ActivityLog::touch();

        return $next($request);
    }
}
