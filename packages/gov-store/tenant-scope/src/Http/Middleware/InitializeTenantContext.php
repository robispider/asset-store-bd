<?php

namespace GovStore\TenantScope\Http\Middleware;

use App\Models\Location;
use Closure;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Contracts\MembershipContextResolver;
use GovStore\TenantScope\Contracts\OrganizationContextResolver;
use GovStore\TenantScope\Services\AssignmentResolver;
use GovStore\TenantScope\Services\CapabilityProfileResolver;
use GovStore\TenantScope\Services\SnipePermissionAdapter;

class InitializeTenantContext
{
    public function __construct(
        protected AssignmentResolver $assignmentResolver,
        protected CapabilityProfileResolver $capabilityResolver,
        protected SnipePermissionAdapter $permissionAdapter
    ) {}

    public function handle($request, Closure $next)
    {
        $context = app(TenantContext::class);
        $context->reset();
        if (! auth()->check()) {
            return $next($request);
        }
        $user = auth()->user();
        $context->isActive = true;
        if ($user->isSuperUser()) {
            $context->isGlobal = true;
            $selection = session('gov_working_membership_id');
            if (is_string($selection) && preg_match('/^ADMIN_MOCK_(\d+)$/', $selection, $matches)) {
                $office = Location::withoutGlobalScopes()->whereNull('deleted_at')->find($matches[1]);
                if ($office) {
                    $context->locationId = (int) $office->id;
                    $context->companyId = $office->company_id ? (int) $office->company_id : null;
                    $context->allowedLocationIds = [$context->locationId];
                    $context->allowedCompanyIds = $context->companyId ? [$context->companyId] : [];
                    $context->isGlobal = false;
                } else {
                    // A stale explicit selection must not silently become global.
                    $context->isGlobal = false;
                    $context->allowedLocationIds = [];
                    $context->allowedCompanyIds = [];
                }
            } elseif ($selection !== null) {
                $membership = app(MembershipContextResolver::class)->workingMembership((int) $user->id, $selection);
                $context->isGlobal = false;
                $context->locationId = $membership['location_id'] ?? null;
                $context->companyId = $membership['company_id'] ?? null;
                $context->membershipId = $membership['id'] ?? null;
                $context->allowedLocationIds = $context->locationId ? [$context->locationId] : [];
                $context->allowedCompanyIds = $context->companyId ? [$context->companyId] : [];
            }

            return $next($request);
        }

        $organization = app(OrganizationContextResolver::class);
        $memberships = app(MembershipContextResolver::class);
        $adminCompany = $organization->companyForAdministrator((int) $user->id);
        $context->isCompanyAdmin = $adminCompany !== null;
        $context->companyId = $adminCompany;
        $selection = session('gov_working_membership_id');
        $membership = $memberships->workingMembership((int) $user->id, $selection);
        if ($membership && (! $adminCompany || $membership['company_id'] === $adminCompany)) {
            $context->membershipId = $membership['id'];
            $context->locationId = $membership['location_id'];
            $context->companyId = $adminCompany ?? $membership['company_id'];
            $context->isHomeOffice = $membership['is_home_office'];
        } elseif ($selection === null && ! $memberships->hasMemberships((int) $user->id)) {
            // Native fallback is only for actors who have never had memberships.
            $office = $user->location_id ? Location::withoutGlobalScopes()->whereNull('deleted_at')->find($user->location_id) : null;
            if ($office && (! $adminCompany || (int) $office->company_id === $adminCompany)) {
                $context->locationId = (int) $office->id;
                $context->companyId = $adminCompany ?? ($office->company_id ? (int) $office->company_id : null);
            }
        }

        if ($context->isCompanyAdmin) {
            $context->allowedLocationIds = Location::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $adminCompany)->pluck('id')->all();
            $context->allowedCompanyIds = [$adminCompany];
        } elseif (($locations = $organization->jurisdictionLocations((int) $user->id)) !== null) {
            $context->allowedLocationIds = $locations;
            $context->allowedCompanyIds = null;
        } else {
            $context->allowedLocationIds = $context->locationId ? [$context->locationId] : [];
            $context->allowedCompanyIds = $context->companyId ? [$context->companyId] : [];
        }

        $role = $this->assignmentResolver->resolveActiveRole((int) $user->id, $context->locationId);
        $context->effectivePermissions = $this->capabilityResolver->resolveSchema($role);
        if ($context->isCompanyAdmin) {
            $context->companyAdminPermissions = $this->capabilityResolver->resolveSchema('company_admin');
            $context->effectivePermissions->merge($context->companyAdminPermissions);
        }
        // Inject the union once; a second injection would overwrite the local role.
        $this->permissionAdapter->adaptAndInject($user, $context->effectivePermissions);

        return $next($request);
    }
}
