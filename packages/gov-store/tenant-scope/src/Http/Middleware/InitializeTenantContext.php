<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Services\AssignmentResolver;
use GovStore\TenantScope\Services\CapabilityProfileResolver;
use GovStore\TenantScope\Services\SnipePermissionAdapter;
use App\Models\Location;

class InitializeTenantContext
{
    protected AssignmentResolver $assignmentResolver;
    protected CapabilityProfileResolver $capabilityResolver;
    protected SnipePermissionAdapter $permissionAdapter;

    public function __construct(
        AssignmentResolver $assignmentResolver,
        CapabilityProfileResolver $capabilityResolver,
        SnipePermissionAdapter $permissionAdapter
    ) {
        $this->assignmentResolver = $assignmentResolver;
        $this->capabilityResolver = $capabilityResolver;
        $this->permissionAdapter = $permissionAdapter;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $context = app(TenantContext::class);

        // 1. Guest bypass
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // 2. Superadmin / Global Bypass
        if ($user->isSuperUser() || ($user->hasAccess('admin') && !$user->company_id)) {
            $context->isActive = true;
            $context->isGlobal = true;
            return $next($request);
        }

        $context->isActive = true;
        $context->isGlobal = false;

        // 3. Resolve Active Working Context (From Session Membership ID)
        $workingLocId = null;
        if ($membershipId = session('gov_working_membership_id')) {
            $membership = OfficeMembership::with('location')->find($membershipId);
            if ($membership) {
                $context->membershipId = $membership->id;
                $context->locationId = $membership->location_id;
                $context->companyId = $membership->location->company_id ?? null;
                $workingLocId = $membership->location_id;
            }
        }
        
        // Fallback for floating/newly created users
        if (!$workingLocId) {
            $context->locationId = $user->location_id;
            $context->companyId = $user->company_id;
            $workingLocId = $user->location_id;
        }

        // 4. Pre-Compute Hierarchy (Allowed Locations for Scopes)
        if ($user->hasAccess('admin') && $user->company_id) {
            // Company Admin: Gets all locations in their Ministry
            $context->allowedLocationIds = Location::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->pluck('id')->toArray();
        } elseif ($jurisdiction = IctJurisdiction::with('geoArea')->where('user_id', $user->id)->first()) {
            // ICT Officer: Gets all locations in their Geographic Tree
            if ($jurisdiction->geoArea) {
                $context->allowedLocationIds = LocationProfile::withoutGlobalScopes()
                    ->whereIn('geo_area_id', function($q) use ($jurisdiction) {
                        $q->select('GeoAreaId')->from('gov_geo_areas')
                          ->where('hid', 'like', $jurisdiction->geoArea->hid . '%');
                    })->pluck('location_id')->toArray();
            } else {
                $context->allowedLocationIds = [];
            }
        } else {
            // Standard Employee: Only sees their active working context
            $context->allowedLocationIds = $workingLocId ? [$workingLocId] : [];
        }

        // =========================================================================
        // 5. RESOLVE RESPONSIBILITY & ADAPT PERMISSIONS (The Phase 4 Hook)
        // =========================================================================
        $roleSlug = $this->assignmentResolver->resolveActiveRole($user->id, $context->locationId);
        $permissionSet = $this->capabilityResolver->resolveSchema($roleSlug);

        // Cache the calculated permission set inside the request-scoped context
        $context->effectivePermissions = $permissionSet;

        // Translate and inject the capability set into Snipe-IT's native model memory space
        $this->permissionAdapter->adaptAndInject($user, $permissionSet);

        return $next($request);
    }
}