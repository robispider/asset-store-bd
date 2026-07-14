<?php

namespace GovStore\TenantScope\Services;

use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\LocationProfile;

class AssignmentResolver
{
    public function resolveActiveRole(int $userId, ?int $locationId): ?string
    {
        if (!$locationId) {
            return null;
        }

        $isOfficeAdmin = LocationProfile::where('location_id', $locationId)
            ->where('office_admin_id', $userId)
            ->exists();

        if ($isOfficeAdmin) {
            return 'office_admin';
        }

        // STRICT PIVOT LOOKUP
        return OfficeResponsibility::where('location_id', $locationId)
            ->where('user_id', $userId)
            ->value('role_slug');
    }
}