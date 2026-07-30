<?php

namespace GovStore\Tracking\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InjectTrackingUi
{
    public function handle(Request $request, Closure $next): Response
    {
        // This middleware now only allows standard request execution.
        // All form fields, layout injections, and capabilities are managed by store-operations.
        return $next($request);
    }
}