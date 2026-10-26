<?php

namespace GovStore\TenantScope\Services;

use GovStore\TenantScope\Contracts\MembershipContextResolver;
use GovStore\TenantScope\Contracts\OrganizationContextResolver;
use Illuminate\Support\Facades\DB;

class AssignmentResolver
{
    /** All current duties in the selected context, in stable attribution order. */
    public function resolveRoles(int $userId, ?int $locationId, ?int $companyId = null): array
    {
        $organization = app(OrganizationContextResolver::class);
        $roles = [];
        $context = app(\GovStore\TenantScope\Contexts\TenantContext::class);
        $liveMembership = ! $context->membershipId || app(MembershipContextResolver::class)->hasActiveMembershipAt($userId, (int) $locationId);
        if ($locationId && $liveMembership) {
            if ($organization->isOfficeAdministrator($userId, $locationId)) {
                $roles[] = 'office_admin';
            }
            $duties = app(MembershipContextResolver::class)->responsibilityRoles($userId, $locationId);
            $cover = DB::table('gov_access_grants')->where('location_id', $locationId)
                ->where('user_id', $userId)->where('expires_at', '>', now())->pluck('role_slug')->all();
            $duties = array_values(array_unique(array_merge($duties, $cover)));
            sort($duties);
            $roles = array_merge($roles, $duties);
        }
        $adminCompany = $organization->companyForAdministrator($userId);
        if ($adminCompany && $adminCompany === $companyId) {
            $roles[] = 'company_admin';
        }
        if ($organization->hasJurisdiction($userId)) {
            $roles[] = 'ict_officer';
        }

        return array_values(array_unique($roles ?: ['employee']));
    }

    /** @deprecated Consumers must compile resolveRoles(), not discard combined duties. */
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
