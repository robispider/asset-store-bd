<?php

namespace GovStore\OfficeMembership\Rules;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;

class NoPendingRequestsRule implements IClearanceRule
{
    public function getName(): string { return 'Pending Service Requests'; }

    public function check(User $user, int $locationId): ClearanceResult
    {
        if (!class_exists(\GovStore\CustomRequests\Models\Request::class)) return new ClearanceResult(true);

        $pendingCount = \GovStore\CustomRequests\Models\Request::where('requested_by', $user->id)
            ->where('delivery_location_id', $locationId)
            ->whereNotIn('approval_status', ['rejected', 'cancelled', 'closed'])
            ->count();

        if ($pendingCount > 0) {
            return new ClearanceResult(false, "You have {$pendingCount} active service request(s) in progress. Please cancel them or wait for fulfillment.");
        }

        return new ClearanceResult(true, "All service requests completed.");
    }
}