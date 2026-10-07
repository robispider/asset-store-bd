<?php

namespace GovStore\TenantScope\Services;

use GovStore\TenantScope\Contracts\MembershipContextResolver;
use GovStore\TenantScope\Contracts\OrganizationContextResolver;
use Illuminate\Support\Facades\DB;

class AssignmentResolver
{
    public function resolveActiveRole(int $userId, ?int $locationId): string
    {
        $organization = app(OrganizationContextResolver::class);
        if ($locationId) {
            if ($organization->isOfficeAdministrator($userId, $locationId)) {
                return 'office_admin';
            }
            $roles = app(MembershipContextResolver::class)->responsibilityRoles($userId, $locationId);
            if ($roles) {
                return $roles[0];
            }
            $temporary = DB::table('gov_access_grants')->where('location_id', $locationId)
                ->where('user_id', $userId)->where('expires_at', '>', now())->value('role_slug');
            if ($temporary) {
                return $temporary;
            }
        }
        if ($organization->companyForAdministrator($userId)) {
            return 'company_admin';
        }
        if ($organization->hasJurisdiction($userId)) {
            return 'ict_officer';
        }

        return 'employee';
    }
}
