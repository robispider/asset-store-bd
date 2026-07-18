<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use App\Models\Asset;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;

class NoActiveAssetsRule implements IClearanceRule
{
    public function getName(): string { return __('office_membership::member.rule_physical_inventory_name'); }

    public function check(User $user, int $locationId): ClearanceResult
    {
        // Check if the user has Snipe-IT assets checked out to them at this specific location
        $assetsCount = Asset::where('assigned_to', $user->id)
                            ->where('location_id', $locationId)
                            ->count();

        if ($assetsCount > 0) {
            return new ClearanceResult(false, __('office_membership::member.rule_assets_held', ['count' => $assetsCount]));
        }

        return new ClearanceResult(true, __('office_membership::member.rule_assets_returned'));
    }
}