<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use GovStore\TenantScope\Contracts\GlobalTenantMaintenance;
use GovStore\TenantScope\Contracts\TenantScopedExecution;
use GovStore\TenantScope\Services\TenantExecution;
use Illuminate\Auth\Access\AuthorizationException;

class ExecuteTenantJob
{
    public function handle($job, Closure $next)
    {
        if (! str_starts_with(get_class($job), 'GovStore\\')) {
            return $next($job);
        }
        $execution = app(TenantExecution::class);
        if ($job instanceof TenantScopedExecution) {
            return $execution->run($job->tenantExecution(), fn () => $next($job));
        }
        if ($job instanceof GlobalTenantMaintenance) {
            return $execution->globalMaintenance(fn () => $next($job));
        }
        throw new AuthorizationException('GovStore jobs must declare their tenant execution contract.');
    }
}
