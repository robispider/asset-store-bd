<?php

namespace GovStore\Experimentation\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\CapabilityProfileResolver;
use Illuminate\Support\Facades\Auth;

class ActorContext
{
    public function run(User $actor, ?Location $office, callable $callback, string $role = 'employee'): mixed
    {
        $previousActor = Auth::user();
        $previousContext = app(TenantContext::class);
        $context = new TenantContext;
        $context->isActive = $office !== null;
        $context->isGlobal = $office === null && $actor->isSuperUser();
        $context->locationId = $office?->id;
        $context->companyId = $office?->company_id;
        $context->allowedLocationIds = $office ? [$office->id] : null;
        $context->allowedCompanyIds = $office ? [$office->company_id] : null;
        $context->isHomeOffice = true;
        $context->effectivePermissions = app(CapabilityProfileResolver::class)->resolveSchema($role);
        app()->instance(TenantContext::class, $context);
        Auth::setUser($actor);
        try {
            // Resolve domain services after binding this operation's tenant context.
            return $callback();
        } finally {
            app()->instance(TenantContext::class, $previousContext);
            if ($previousActor) {
                Auth::setUser($previousActor);
            } else {
                Auth::forgetUser();
            }
        }
    }
}
