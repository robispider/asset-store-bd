<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use GovStore\TenantScope\Contexts\TenantContext;

class InitializeTenantContext
{
    public function handle($request, Closure $next)
    {
        // 1. Resolve Singleton Context
        $context = app(TenantContext::class);

        // 2. Ignore CLI, Guests, or Superadmins
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();
        if ($user->isSuperUser() || Gate::allows('admin') || Gate::allows('superadmin')) {
            return $next($request); // Admins remain unbound
        }

        // 3. Populate Immutable Context Properties
        $context->isActive = true;
        $context->companyId = $user->company_id;
        $context->locationId = $user->location_id;

        // 4. Cache configurations to completely eliminate DB lookups on subsequent loads
        $context->configs = Cache::remember('tenant_scope_configs', 3600, function () {
            // Using DB::table bypasses Eloquent booting entirely
            return DB::table('gov_tenant_scopes')->get()->keyBy('reference_type')->toArray();
        });

        return $next($request);
    }
}