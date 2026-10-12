<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\OfficeMembership\Services\ClearanceResult;
use GovStore\Organization\Models\LocationProfile;
use Illuminate\Support\Facades\DB;

class NoActiveRolesRule implements IClearanceRule
{
    public function getName(): string
    {
        return __('office_membership::member.rule_office_responsibility_name');
    }

    public function check(User $user, int $locationId): ClearanceResult
    {
        // Query the new responsibilities pivot matrix
        $count = fn ($q) => DB::transactionLevel() > 0 ? $q->lockForUpdate()->get()->count() : $q->count();
        $activeResponsibilitiesCount = $count(OfficeResponsibility::where('location_id', $locationId)
            ->where('user_id', $user->id));

        $activeResponsibilitiesCount += $count(LocationProfile::where('location_id', $locationId)->where('office_admin_id', $user->id));
        $activeResponsibilitiesCount += $count(DB::table('gov_access_grants')->where('location_id', $locationId)
            ->where('user_id', $user->id)->where('expires_at', '>', now()));

        if ($activeResponsibilitiesCount > 0) {
            return new ClearanceResult(
                false,
                __('office_membership::member.rule_roles_held', ['count' => $activeResponsibilitiesCount])
            );
        }

        return new ClearanceResult(true, __('office_membership::member.rule_no_blocking_roles'));
    }
}
