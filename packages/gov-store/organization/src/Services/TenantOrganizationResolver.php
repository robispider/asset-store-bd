<?php

namespace GovStore\Organization\Services;

use GovStore\Organization\Models\CompanyAdmin;
use GovStore\Organization\Models\IctJurisdiction;
use GovStore\Organization\Models\LocationProfile;
use GovStore\TenantScope\Contracts\OrganizationContextResolver;

class TenantOrganizationResolver implements OrganizationContextResolver
{
    public function companyForAdministrator(int $userId): ?int
    {
        $id = CompanyAdmin::where('user_id', $userId)->value('company_id');

        return $id ? (int) $id : null;
    }

    public function jurisdictionLocations(int $userId): ?array
    {
        $jurisdictions = IctJurisdiction::with('geoArea')->where('user_id', $userId)->get();
        if ($jurisdictions->isEmpty()) {
            return null;
        }
        $ids = [];
        foreach ($jurisdictions as $jurisdiction) {
            if (! $jurisdiction->geoArea || ! $jurisdiction->geoArea->hid) {
                continue;
            }
            $ids = array_merge($ids, LocationProfile::withoutGlobalScopes()
                ->whereIn('geo_area_id', function ($query) use ($jurisdiction) {
                    $query->select('GeoAreaId')->from('gov_geo_areas')
                        ->where('hid', 'like', $jurisdiction->geoArea->hid.'%');
                })->pluck('location_id')->all());
        }

        return array_values(array_unique(array_map('intval', $ids)));
    }

    public function isOfficeAdministrator(int $userId, int $locationId): bool
    {
        return LocationProfile::where('location_id', $locationId)->where('office_admin_id', $userId)->exists();
    }

    public function isCompanyAdministrator(int $userId, int $companyId): bool
    {
        return CompanyAdmin::where('user_id', $userId)->where('company_id', $companyId)->exists();
    }

    public function hasJurisdiction(int $userId): bool
    {
        return IctJurisdiction::where('user_id', $userId)->exists();
    }

    public function officeAdministratorIds(int $locationId): array
    {
        return LocationProfile::where('location_id', $locationId)->pluck('office_admin_id')->all();
    }
}
