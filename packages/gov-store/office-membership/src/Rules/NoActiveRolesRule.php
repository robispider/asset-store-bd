<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;
use GovStore\OfficeMembership\Models\OfficeResponsibility;

class NoActiveRolesRule implements IClearanceRule
{
    public function getName(): string { return __('office_membership::member.rule_office_responsibility_name'); }

    public function check(User $user, int $locationId): ClearanceResult
    {
        // Query the new responsibilities pivot matrix
        $activeResponsibilitiesCount = OfficeResponsibility::where('location_id', $locationId)
            ->where('user_id', $user->id)
            ->count();

        if ($activeResponsibilitiesCount > 0) {
            return new ClearanceResult(
                false, 
                __('office_membership::member.rule_roles_held', ['count' => $activeResponsibilitiesCount])
            );
        }

        return new ClearanceResult(true, __('office_membership::member.rule_no_blocking_roles'));
    }
}