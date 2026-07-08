<?php

namespace GovStore\Organization\Http\Middleware;

use Closure;
use GovStore\Organization\Models\IctJurisdiction;

class EnsureUserIsIctOfficer
{
    public function handle($request, Closure $next)
    {
        // GUEST SAFE GUARD: Pass standard guest/login calls downstream cleanly
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // Superadmins bypass geographic tag checks
        if ($user->isSuperUser() || $user->hasAccess('admin')) {
            return $next($request);
        }

        // Checks if user has an active geographical boundary assignment (the ICTOfficerGeo tag)
        $isIctOfficer = IctJurisdiction::where('user_id', $user->id)->exists();

        if (!$isIctOfficer) {
            abort(403, 'Access Denied: Office provisioning requires an active ICT Officer jurisdiction assignment.');
        }

        return $next($request);
    }
}