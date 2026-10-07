<?php

namespace GovStore\Organization\Http\Middleware;

use Closure;
use GovStore\Organization\Services\OfficeRequestIntake;
use GovStore\TenantScope\Contexts\TenantContext;

class EnsureOfficeRequestIntake
{
    public function handle($request, Closure $next)
    {
        if (! $request->is('gov-requests/catalog', 'gov-requests/catalog/*', 'gov-requests/basket', 'gov-requests/basket/*')) {
            return $next($request);
        }
        $officeId = app(TenantContext::class)->locationId;
        if ($officeId) {
            app(OfficeRequestIntake::class)->assertOpen([$officeId]);
        }

        return $next($request);
    }
}
