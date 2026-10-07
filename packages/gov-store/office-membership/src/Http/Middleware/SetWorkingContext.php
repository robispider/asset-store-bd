<?php

namespace GovStore\OfficeMembership\Http\Middleware;

use Closure;
use GovStore\TenantScope\Contracts\MembershipContextResolver;

class SetWorkingContext
{
    public function handle($request, Closure $next)
    {
        if (auth()->check()) {
            $user = auth()->user();
            if (session('gov_working_user_id') !== $user->id) {
                session()->forget('gov_working_membership_id');
                session()->put('gov_working_user_id', $user->id);
            }
            if (! $user->isSuperUser() && ! session()->has('gov_working_membership_id')) {
                $membership = app(MembershipContextResolver::class)->workingMembership((int) $user->id, null);
                if ($membership) {
                    session()->put('gov_working_membership_id', $membership['id']);
                }
            }
        }

        return $next($request);
    }
}
