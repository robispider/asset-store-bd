<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;

class NoPendingRequestsRule implements IClearanceRule
{
    public function getName(): string { return __('office_membership::member.rule_pending_requests_name'); }

    public function check(User $user, int $locationId): ClearanceResult
    {
        if (!class_exists(\GovStore\CustomRequests\Models\Request::class)) {
            return new ClearanceResult(true);
        }

        // Checks if they have active requests in progress
        $pendingCount = \GovStore\CustomRequests\Models\Request::where('requested_by', $user->id)
            ->where('delivery_location_id', $locationId)
            ->whereNotIn('approval_status', ['rejected', 'cancelled', 'closed'])
            ->count();

        if ($pendingCount > 0) {
            return new ClearanceResult(false, __('office_membership::member.rule_requests_active', ['count' => $pendingCount]));
        }

        return new ClearanceResult(true, __('office_membership::member.rule_requests_completed'));
    }
}