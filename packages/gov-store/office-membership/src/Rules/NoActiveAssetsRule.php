<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use App\Models\Asset;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;

class NoActiveAssetsRule implements IClearanceRule
{
    public function getName(): string { return 'Physical Inventory Check'; }

    public function check(User $user, int $locationId): ClearanceResult
    {
        // Check if the user has Snipe-IT assets checked out to them at this specific location
        $assetsCount = Asset::where('assigned_to', $user->id)
                            ->where('location_id', $locationId)
                            ->count();

        if ($assetsCount > 0) {
            return new ClearanceResult(false, "You currently hold {$assetsCount} active asset(s). You must check them back into the storekeeper.");
        }

        return new ClearanceResult(true, "All physical assets returned.");
    }
}