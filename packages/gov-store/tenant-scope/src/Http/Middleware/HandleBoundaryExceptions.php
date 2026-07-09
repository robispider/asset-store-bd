<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use GovStore\TenantScope\Exceptions\TenantBoundaryException;

class HandleBoundaryExceptions
{
    public function handle($request, Closure $next)
    {
        try {
            return $next($request);
        } catch (TenantBoundaryException $e) {
            
            // If it's an API request, return a clean JSON 403 response
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $e->getMessage()
                ], 403);
            }

            // For standard web interactions, bounce them back with a flash alert
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}