<?php

namespace GovStore\Organization\Http\Middleware;

use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Services\OfficeReadinessService;

class EnsureOfficeIsOperational
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        // 1. Exception-Safe: Standard Laravel gates bypass operational checks
        if ($user->isSuperUser() || Gate::allows('admin') || Gate::allows('superadmin')) {
            return $next($request);
        }

        $path = $request->path();
        
        // 2. We only intercept catalog browsing and shopping basket actions
        $isTargetRoute = (str_contains($path, 'gov-requests/catalog') || str_contains($path, 'gov-requests/basket')) 
                         && !str_contains($path, 'my-requests');

        if ($isTargetRoute) {
            
            // Safety Intercept: User is not assigned to any physical office building
            if (!$user->location_id) {
                return response()->view('govorg::readiness.unassigned');
            }

            $profile = LocationProfile::where('location_id', $user->location_id)->first();

            // 3. Intercept if the location profile has not met operational criteria
            if (!$profile || $profile->lifecycle_status !== 'operational') {
                $readinessService = app(OfficeReadinessService::class);
                $readiness = $readinessService->evaluateAndTransition($user->location_id);
                $location = $user->location;

                // Fallback check if the Snipe-IT Location model was deleted or is orphaned
                if (!$location) {
                    return response()->view('govorg::readiness.unassigned');
                }

                return response()->view('govorg::readiness.waiting', compact('profile', 'location', 'readiness'));
            }
        }

        return $next($request);
    }
}