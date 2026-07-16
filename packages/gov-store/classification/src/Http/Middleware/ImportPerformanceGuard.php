<?php

namespace GovStore\Classification\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ImportPerformanceGuard
{
    /**
     * Prepares the PHP environment for heavy, chunked data ingestion.
     */
    public function handle(Request $request, Closure $next)
    {
        // 1. Prevent timeouts during heavy IO operations
        ini_set('max_execution_time', '600');
        ignore_user_abort(true);

        // 2. Disable Laravel Query Logging (prevents DB RAM leakage)
        DB::disableQueryLog();

        // 3. Disable Developer Tools if they exist in the container
        if (app()->bound('debugbar')) {
            app('debugbar')->disable();
        }

        if (class_exists(\Laravel\Telescope\Telescope::class)) {
            \Laravel\Telescope\Telescope::stopRecording();
        }

        $response = $next($request);

        // 4. Restore DB Logging
        DB::enableQueryLog();

        return $response;
    }
}