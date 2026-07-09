<?php

namespace GovStore\OfficeMembership\Http\Middleware;

use Closure;
use GovStore\OfficeMembership\Models\OfficeMembership;

class SetWorkingContext
{
    public function handle($request, Closure $next)
    {
        // 1. Guest bypass
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // 2. USER MISMATCH SHIELD: 
        // If the logged-in user ID does not match the active session owner, 
        // instantly purge any old working context to prevent cross-tenant data leaks!
        if (session('gov_working_user_id') !== $user->id) {
            session()->forget('gov_working_location_id');
            session()->put('gov_working_user_id', $user->id);
        }

        // 3. Resolve and lock default home office membership if empty
        if (!session()->has('gov_working_location_id')) {
            $defaultMembership = OfficeMembership::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('is_home_office', true)
                ->first();

            // Fallback to Snipe-IT's core home office location if no membership exists yet
            $activeLocationId = $defaultMembership ? $defaultMembership->location_id : $user->location_id;

            if ($activeLocationId) {
                session()->put('gov_working_location_id', $activeLocationId);
            }
        }

        return $next($request);
    }
}