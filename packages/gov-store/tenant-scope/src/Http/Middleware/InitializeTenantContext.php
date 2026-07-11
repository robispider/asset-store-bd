<?php

namespace GovStore\TenantScope\Http\Middleware;

use Closure;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\LocationProfile;
use App\Models\Location;

class InitializeTenantContext
{
    public function handle($request, Closure $next)
    {
        $context = app(TenantContext::class);

        if (!auth()->check()) {
            return $next($request);
        }

        $user = auth()->user();

        // 1. Superadmin / Global Bypass
        if ($user->isSuperUser() || ($user->hasAccess('admin') && !$user->company_id)) {
            $context->isActive = true;
            $context->isGlobal = true;
            return $next($request);
        }

        $context->isActive = true;
        $context->isGlobal = false;

        // 2. Resolve Active Working Context (For operational data like Assets)
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
        
        // Fallback for new/floating users
        if (!$workingLocId) {
            $context->locationId = $user->location_id;
            $context->companyId = $user->company_id;
            $workingLocId = $user->location_id;
        }

        // 3. Pre-Compute Hierarchy (For viewing Users and Offices)
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

        return $next($request);
    }
}