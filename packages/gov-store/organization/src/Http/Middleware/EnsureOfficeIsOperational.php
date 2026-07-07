<?php

namespace GovStore\Organization\Http\Middleware;

use Closure;
use Illuminate\Http\Response;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Services\OfficeReadinessService;

class EnsureOfficeIsOperational
{
    public function handle($request, Closure $next)
    {
        $user = auth()->user();

        // 1. Superadmins/ICT Officers bypass operational status gates
        if ($user->isSuperUser() || $user->hasAccess('admin')) {
            return $next($request);
        }

        // 2. We only intercept catalog browsing and shopping basket actions
        $path = $request->path();
        $isTargetRoute = str_contains($path, 'gov-requests/catalog') || 
                         str_contains($path, 'gov-requests/basket');

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

                return response()->view('govorg::readiness.waiting', compact('profile', 'location', 'readiness'));
            }
        }

        return $next($request);
    }
}