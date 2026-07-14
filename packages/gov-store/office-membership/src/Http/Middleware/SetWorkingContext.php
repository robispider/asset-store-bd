<?php

namespace GovStore\OfficeMembership\Http\Middleware;

use Closure;
use GovStore\OfficeMembership\Models\OfficeMembership;

class SetWorkingContext
{
    public function handle($request, Closure $next)
    {
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // Clear stale session contexts if a new user logs in
        if (session('gov_working_user_id') !== $user->id) {
            session()->forget('gov_working_membership_id');
            session()->put('gov_working_user_id', $user->id);
        }

        if (!session()->has('gov_working_membership_id')) {
            
            // 1. Resolve Active Home Membership strictly
            $membership = OfficeMembership::where('user_id', $user->id)
                ->where('status', 'active')
                ->orderByDesc('is_home_office') // Forces True (1) before False (0)
                ->orderBy('created_at', 'asc') // Deterministic tie-breaker
                ->first();

            if ($membership) {
                session()->put('gov_working_membership_id', $membership->id);
                
                // Keep native cache synchronized (Self-healing alignment)
                if ((int)$user->location_id !== (int)$membership->location_id) {
                    $user->location_id = $membership->location_id;
                    $user->saveQuietly(); // Saves without triggering observers
                }
            } 
            // 2. Fallback ONLY if they have zero memberships (New native user)
            else if ($user->location_id) {
                // Do not set working_membership_id, allowing InitializeTenantContext to fallback safely
            }
        }

        return $next($request);
    }
}