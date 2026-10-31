<?php

namespace GovStore\Experimentation\Services;

use App\Models\Location;
use App\Models\User;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\CapabilityProfileResolver;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\TenantScope\Services\TenantExecution;
use Illuminate\Support\Facades\Auth;

class ActorContext
{
    public function run(User $actor, ?Location $office, callable $callback, string $role = 'employee'): mixed
    {
        $execution = app(TenantExecution::class);
        if (! $office) {
            app(ExperimentAccess::class)->authorize($actor);

            return $execution->globalMaintenance(function () use ($actor, $callback) {
                Auth::setUser($actor);
                app(TenantContext::class)->isGlobal = true;

                return $callback();
            });
        }
        $ability = match ($role) {
            'storekeeper' => 'storeops.documents.post',
            'primary_approver' => 'requests.approve.primary',
            'final_approver' => 'requests.approve.final',
            'office_admin' => 'organization.office.manage',
            default => 'requests.submit',
        };

        return $execution->run(['actor_id' => $actor->id, 'scope_type' => 'location',
            'scope_id' => $office->id, 'ability' => $ability], function () use ($actor, $office, $callback) {
                $context = app(TenantContext::class);
                $roles = app(GovAccess::class)->roles(Auth::user());
                $resolved = collect(['storekeeper', 'primary_approver', 'final_approver', 'office_admin'])
                    ->first(fn ($candidate) => in_array($candidate, $roles, true));
                $context->isHomeOffice = (int) $actor->location_id === (int) $office->id;
                $context->effectivePermissions = app(CapabilityProfileResolver::class)->resolveSchema($resolved);

                return $callback();
            });
    }
}
