<?php

namespace GovStore\Experimentation\Http\Middleware;

use Closure;
use GovStore\Experimentation\Services\ExperimentAccess;
use Illuminate\Http\Request;

class RequireExperimentAccess
{
    public function handle(Request $request, Closure $next)
    {
        // The host app's global superuser Gate bypass must not bypass the environment switch.
        app(ExperimentAccess::class)->authorize($request->user());

        return $next($request);
    }
}
