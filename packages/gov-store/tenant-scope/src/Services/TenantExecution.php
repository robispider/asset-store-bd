<?php

namespace GovStore\TenantScope\Services;

use App\Models\Company;
use App\Models\Location;
use App\Models\User;
use Closure;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Contracts\MembershipContextResolver;
use GovStore\TenantScope\Contracts\OrganizationContextResolver;
use Illuminate\Auth\Access\AuthorizationException;

class TenantExecution
{
    /** Re-resolve live identity and authorization at execution, including delayed retries. */
    public function run(array $specification, Closure $callback): mixed
    {
        $context = app(TenantContext::class);
        $previous = clone $context;
        $previousActor = auth()->user();
        $context->reset();
        $context->isActive = true;
        $context->allowedLocationIds = [];
        $context->allowedCompanyIds = [];
        auth()->forgetUser();
        try {
            $actorId = filter_var($specification['actor_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $id = filter_var($specification['scope_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if (! $actorId || ! $id) {
                throw new AuthorizationException('Valid execution actor and target IDs are required.');
            }
            $actor = User::withoutGlobalScopes()->find($actorId);
            if (! $actor || ! $actor->activated || $actor->deleted_at) {
                throw new AuthorizationException('The execution actor is unavailable.');
            }
            $organization = app(OrganizationContextResolver::class);
            $type = $specification['scope_type'] ?? '';
            if ($type === 'location') {
                $office = Location::withoutGlobalScopes()->whereNull('deleted_at')->find($id);
                if (! $office || ! $office->company_id) {
                    throw new AuthorizationException('The execution office is unavailable.');
                }
                $context->locationId = (int) $office->id;
                $context->companyId = (int) $office->company_id;
                $context->allowedLocationIds = [$context->locationId];
                $context->allowedCompanyIds = [$context->companyId];
                if (! $actor->isSuperUser()
                    && ! app(MembershipContextResolver::class)->hasActiveMembershipAt((int) $actor->id, $id)
                    && ! $organization->isOfficeAdministrator((int) $actor->id, $id)
                    && $organization->companyForAdministrator((int) $actor->id) !== $context->companyId) {
                    throw new AuthorizationException('The actor no longer belongs to the execution office.');
                }
            } elseif ($type === 'company' && Company::withoutGlobalScopes()->whereKey($id)->exists()) {
                if (! $actor->isSuperUser() && $organization->companyForAdministrator((int) $actor->id) !== $id) {
                    throw new AuthorizationException('The actor cannot execute for this company.');
                }
                $context->companyId = $id;
                $context->isCompanyAdmin = true;
                $context->allowedCompanyIds = [$id];
                $context->allowedLocationIds = Location::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $id)->pluck('id')->all();
            } else {
                throw new AuthorizationException('An explicit office or company execution target is required.');
            }
            auth()->setUser($actor);
            if (! app(GovAccess::class)->decide($actor, $specification['ability'] ?? '')->allowed) {
                throw new AuthorizationException('The execution ability is no longer granted.');
            }

            return $callback();
        } finally {
            foreach (get_object_vars($previous) as $property => $value) {
                $context->$property = $value;
            }
            auth()->forgetUser();
            if ($previousActor) {
                auth()->setUser($previousActor);
            }
        }
    }

    public function globalMaintenance(Closure $callback): mixed
    {
        $context = app(TenantContext::class);
        $previous = clone $context;
        $previousActor = auth()->user();
        $context->reset();
        auth()->forgetUser();
        try {
            return $callback();
        } finally {
            foreach (get_object_vars($previous) as $property => $value) {
                $context->$property = $value;
            }
            if ($previousActor) {
                auth()->setUser($previousActor);
            } else {
                auth()->forgetUser();
            }
        }
    }
}
