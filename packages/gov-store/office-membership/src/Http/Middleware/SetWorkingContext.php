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

        // 2. Clear stale session contexts if a new user logs in
        if (session('gov_working_user_id') !== $user->id) {
            session()->forget('gov_working_membership_id');
            session()->put('gov_working_user_id', $user->id);
        }

        // 3. Resolve and store the default home office membership if empty
        if (!session()->has('gov_working_membership_id')) {
            $defaultMembership = OfficeMembership::where('user_id', $user->id)
                ->where('status', 'active')
                ->where('is_home_office', true)
                ->first();

            // Fallback to their first active membership if no home base is flagged
            if (!$defaultMembership) {
                $defaultMembership = OfficeMembership::where('user_id', $user->id)
                    ->where('status', 'active')
                    ->first();
            }

            if ($defaultMembership) {
                session()->put('gov_working_membership_id', $defaultMembership->id);
            }
        }

        return $next($request);
    }
}