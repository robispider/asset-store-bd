<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;
use GovStore\OfficeMembership\Models\OfficeResponsibility;

class NoActiveRolesRule implements IClearanceRule
{
    public function getName(): string { return 'Office Responsibility Check'; }

    public function check(User $user, int $locationId): ClearanceResult
    {
        // Query the new responsibilities pivot matrix
        $activeResponsibilitiesCount = OfficeResponsibility::where('location_id', $locationId)
            ->where('user_id', $user->id)
            ->count();

        if ($activeResponsibilitiesCount > 0) {
            return new ClearanceResult(
                false, 
                "You currently hold {$activeResponsibilitiesCount} administrative/storekeeper responsibility here. You must delegate this custody to a colleague first."
            );
        }

        return new ClearanceResult(true, "No blocking administrative roles.");
    }
}