<?php

namespace GovStore\Classification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportPerformanceGuard
{
    public function handle(Request $request, Closure $next)
    {
        ini_set('max_execution_time', '600');
        ignore_user_abort(true);

        DB::disableQueryLog();

        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $response = $next($request);

        DB::enableQueryLog();

        return $response;
    }
}