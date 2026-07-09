<?php

namespace GovStore\Organization\Http\Middleware;

use Closure;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Services\OfficeReadinessService;

class EnsureOfficeIsOperational
{
    public function handle($request, Closure $next)
    {
        // 1. GUEST SAFE GUARD: Pass standard guest/login calls downstream cleanly
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // 2. Admins/superusers bypass operational checks.
        //    Snipe-IT authorises via User::hasAccess(), not named Gate abilities.
        if ($user->isSuperUser() || $user->hasAccess('admin') || $user->hasAccess('superuser')) {
            return $next($request);
        }

        $path = $request->path();
        
        // 3. We only intercept catalog browsing and shopping basket actions
        $isTargetRoute = (str_contains($path, 'gov-requests/catalog') || str_contains($path, 'gov-requests/basket')) 
                         && !str_contains($path, 'my-requests');

        if ($isTargetRoute) {
            
            // Safety Intercept: User is not assigned to any physical office building
            if (!$user->location_id) {
                return response()->view('govorg::readiness.unassigned');
            }

            try {
                $profile = LocationProfile::where('location_id', $user->location_id)->first();

                // 4. Intercept if the location profile has not met operational criteria
                if (!$profile || $profile->lifecycle_status !== 'operational') {
                    // Read-only evaluation — a GET request/middleware must not write.
                    $readinessService = app(OfficeReadinessService::class);
                    $readiness = $readinessService->evaluate($user->location_id);
                    $location = $user->location;

                    // Fallback check if the Snipe-IT Location model was deleted or is orphaned
                    if (!$location) {
                        return response()->view('govorg::readiness.unassigned');
                    }

                    return response()->view('govorg::readiness.waiting', compact('profile', 'location', 'readiness'));
                }
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                // If a ModelNotFound is thrown, render the unconfigured warnings page
                $location = $user->location ?: \App\Models\Location::find($user->location_id);
                if (!$location) {
                    return response()->view('govorg::readiness.unassigned');
                }
                
                $profile = LocationProfile::where('location_id', $user->location_id)->first();
                $readiness = [
                    'is_operational' => false,
                    'checklist' => [
                        'has_office_admin' => $profile && !is_null($profile->office_admin_id),
                        'has_primary_approver' => false,
                        'has_storekeeper' => false,
                        'has_users' => true
                    ],
                    'users_count' => 1
                ];
                return response()->view('govorg::readiness.waiting', compact('profile', 'location', 'readiness'));
            } catch (\Exception $e) {
                return response()->view('govorg::readiness.unassigned');
            }
        }

        return $next($request);
    }
}