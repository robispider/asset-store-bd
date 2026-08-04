<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\CompanyAdmin;
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

    public function handle($request, Closure $next)
    {
        $context = app(TenantContext::class);

        // 1. Guest bypass
        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // 2. Superadmin / Global Bypass
        // Note: Superadmins shouldn't be locked into a Company Admin constraint.
        if ($user->isSuperUser() || ($user->hasAccess('admin') && !$user->company_id)) {
            $context->isActive = true;
            $context->isGlobal = true;
            return $next($request);
        }

        $context->isActive = true;
        $context->isGlobal = false;

        // NEW 3. Resolve Company Admin Operations
        $companyAdmin = CompanyAdmin::where('user_id', $user->id)->first();
        if ($companyAdmin) {
            $context->isCompanyAdmin = true;
            $context->companyId = $companyAdmin->company_id; // Restrict context to Admin's company
        }

        // 4. Resolve Active Working Context (For Local Offices)
        $workingLocId = null;
        if ($membershipId = session('gov_working_membership_id')) {
            $membership = OfficeMembership::with('location')->find($membershipId);
            if ($membership) {
                $context->membershipId = $membership->id;
                $context->locationId = $membership->location_id;
                
                // Only inherit the location's company if the user is NOT restricted as a Company Admin
                if (!$context->isCompanyAdmin) {
                    $context->companyId = $membership->location->company_id ?? null;
                }
                $workingLocId = $membership->location_id;
            }
        }
        
        // Fallback for native/new users
        if (!$workingLocId) {
            $context->locationId = $user->location_id;
            if (!$context->isCompanyAdmin) {
                $context->companyId = $user->company_id;
            }
            $workingLocId = $user->location_id;
        }

        // 5. Pre-Compute Hierarchy (Allowed Locations & Companies)
        if ($context->isCompanyAdmin) {
            
            // Company Admin: Gets all locations strictly within their assigned Company.
            $context->allowedLocationIds = Location::withoutGlobalScopes()
                ->where('company_id', $context->companyId)
                ->pluck('id')->toArray();
                
            $context->allowedCompanyIds = [$context->companyId];

        } elseif ($user->hasAccess('admin') && $user->company_id) {
            
            // Legacy Native Snipe-IT Company Admin setup
            $context->allowedLocationIds = Location::withoutGlobalScopes()
                ->where('company_id', $user->company_id)
                ->pluck('id')->toArray();
                
            $context->allowedCompanyIds = null;

        } elseif ($jurisdiction = IctJurisdiction::with('geoArea')->where('user_id', $user->id)->first()) {
            
            // ICT Officer: Gets all locations within their Geographic Tree. Sees ALL Companies.
            if ($jurisdiction->geoArea) {
                $context->allowedLocationIds = LocationProfile::withoutGlobalScopes()
                    ->whereIn('geo_area_id', function($q) use ($jurisdiction) {
                        $q->select('GeoAreaId')->from('gov_geo_areas')
                          ->where('hid', 'like', $jurisdiction->geoArea->hid . '%');
                    })->pluck('location_id')->toArray();
            } else {
                $context->allowedLocationIds = [];
            }
            
            $context->allowedCompanyIds = null; 

        } else {
            // Standard Employee/Storekeeper/Approver: Only sees their active working context
            $context->allowedLocationIds = $workingLocId ? [$workingLocId] : [];
            
            $context->allowedCompanyIds = $context->companyId ? [$context->companyId] : [];
        }

        // 6. RESOLVE RESPONSIBILITY & ADAPT BASE PERMISSIONS
        $roleSlug = $this->assignmentResolver->resolveActiveRole($user->id, $context->locationId);
        $permissionSet = $this->capabilityResolver->resolveSchema($roleSlug);
        
        $context->effectivePermissions = $permissionSet;

        // Apply base permissions (ex: Storekeeper or Approver capabilities)
        $this->permissionAdapter->adaptAndInject($user, $permissionSet);

        // NEW 7. LAYER COMPANY ADMIN PERMISSIONS
        // If they are a company admin, append 'company_operations' onto their profile
        if ($context->isCompanyAdmin) {
            $companyAdminPerms = $this->capabilityResolver->resolveSchema('company_admin');
            $context->companyAdminPermissions = $companyAdminPerms;
            
            // Merge into active set if internal merge method exists
            if ($context->effectivePermissions && method_exists($context->effectivePermissions, 'merge')) {
                $context->effectivePermissions->merge($companyAdminPerms);
            }

            // Layer the additional `company_operations` permission arrays into the User object
            $this->permissionAdapter->adaptAndInject($user, $companyAdminPerms);
        }

        return $next($request);
    }
}