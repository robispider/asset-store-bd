<?php

namespace GovStore\TenantScope\Services;

use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\CompanyAdmin; // NEW IMPORT
use Illuminate\Support\Facades\Cache;

class AssignmentResolver
{
    /**
     * Resolves the highest-priority operational role slug for a user in a specific location context.
     * The returned slug maps to a Capability Profile in config/permissions.php.
     */
    public function resolveActiveRole(int $userId, ?int $locationId): string
    {
        $cacheKey = "gov_user_role_{$userId}_loc_{$locationId}";

        return Cache::remember($cacheKey, 60, function () use ($userId, $locationId) {

            // 1. Check for Organizational Overseer (Company Admin) - Highest Priority
            $isCompanyAdmin = CompanyAdmin::where('user_id', $userId)->exists();
            if ($isCompanyAdmin) {
                return 'company_admin';
            }

            // 2. Check for ICT Officer (Geographical Overseer)
            $isIctOfficer = IctJurisdiction::where('user_id', $userId)->exists();
            if ($isIctOfficer) {
                return 'ict_officer';
            }

            // 3. Check for Local Office Administrator
            if ($locationId) {
                $isOfficeAdmin = LocationProfile::where('location_id', $locationId)
                    ->where('office_admin_id', $userId)
                    ->exists();

                if ($isOfficeAdmin) {
                    return 'office_admin';
                }

                // 4. Check for Local Operational Roles (Storekeeper / Approvers)
                $responsibility = OfficeResponsibility::where('location_id', $locationId)
                    ->where('user_id', $userId)
                    ->first();

                if ($responsibility) {
                    return $responsibility->role_slug; // Returns 'storekeeper', 'primary_approver', etc.
                }
            }

            // 5. Fallback Default
            return 'employee';
        });
    }
}