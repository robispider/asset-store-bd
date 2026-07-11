<?php

namespace GovStore\TenantScope\Services;

use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\LocationProfile;

class AssignmentResolver
{
    /**
     * Resolves the active responsibility slug for a user at a given location.
     */
    public function resolveActiveRole(int $userId, ?int $locationId): ?string
    {
        if (!$locationId) {
            return null;
        }

        // 1. Check if the user is the designated Office Administrator in the location profile
        $isOfficeAdmin = LocationProfile::where('location_id', $locationId)
            ->where('office_admin_id', $userId)
            ->exists();

        if ($isOfficeAdmin) {
            return 'office_admin';
        }

        // 2. Otherwise, check standard responsibilities (Storekeeper, Approver) from pivot matrix
        return OfficeResponsibility::where('location_id', $locationId)
            ->where('user_id', $userId)
            ->value('role_slug');
    }
}