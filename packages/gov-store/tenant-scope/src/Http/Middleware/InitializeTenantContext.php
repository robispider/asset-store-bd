<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use GovStore\TenantScope\Contexts\TenantContext;

class InitializeTenantContext
{
    public function handle($request, Closure $next)
    {
        // 1. Resolve Singleton Context container
        $context = app(TenantContext::class);

        // 2. Ignore CLI runs or Guests
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // 3. Superadmins completely bypass tenant scopes (Global administrative rights)
        if ($user->isSuperUser()) {
            return $next($request);
        }

        // 4. Lock standard Office Admin and Employee boundaries into memory
        $context->isActive = true;
        $context->companyId = $user->company_id;
        $context->locationId = $user->location_id;

        // 5. Query and Cache configs once (Using Raw DB to completely prevent Eloquent loops)
        $context->configs = Cache::remember('tenant_scope_configs', 3600, function () {
            $rawConfigs = DB::table('gov_tenant_scopes')->get();
            
            $formatted = [];
            foreach ($rawConfigs as $cfg) {
                $formatted[$cfg->reference_type] = (object)[
                    'scope_strategy' => $cfg->scope_strategy,
                    'show_only_used' => (bool)$cfg->show_only_used
                ];
            }
            return $formatted;
        });

        return $next($request);
    }
}