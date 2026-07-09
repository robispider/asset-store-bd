<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;

class NoActiveRolesRule implements IClearanceRule
{
    public function getName(): string { return 'Office Responsibility Check'; }

    public function check(User $user, int $locationId): ClearanceResult
    {
        // Check if the organization package's mapping tables are present on disk
        if (!class_exists(\GovStore\Organization\Models\LocationRole::class)) {
            return new ClearanceResult(true);
        }

        $profile = \GovStore\Organization\Models\LocationProfile::where('location_id', $locationId)->first();
        $roles = \GovStore\Organization\Models\LocationRole::where('location_id', $locationId)->first();

        // Check if the user is currently registered as the Admin, Approver, or Storekeeper for this location
        $hasRole = ($profile && $profile->office_admin_id === $user->id) ||
                   ($roles && (
                       $roles->primary_approver_id === $user->id || 
                       $roles->final_approver_id === $user->id || 
                       $roles->storekeeper_id === $user->id
                   ));

        if ($hasRole) {
            return new ClearanceResult(false, "You hold an active administrative or storekeeper role. You must delegate this responsibility to a colleague first.");
        }

        return new ClearanceResult(true, "No blocking administrative roles.");
    }
}