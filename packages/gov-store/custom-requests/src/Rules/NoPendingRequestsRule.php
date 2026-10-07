<?php

namespace GovStore\CustomRequests\Rules;

use App\Models\User;
use GovStore\CustomRequests\Models\Request;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;
use Illuminate\Support\Facades\DB;

class NoPendingRequestsRule implements IClearanceRule
{
    public function getName(): string
    {
        return __('office_membership::member.rule_pending_requests_name');
    }

    public function check(User $user, int $locationId): ClearanceResult
    {
        if (! class_exists(Request::class)) {
            return new ClearanceResult(true);
        }

        // Checks if they have active requests in progress
        $pending = Request::withTrashed()->where('requested_by', $user->id)
            ->where(fn ($q) => $q->where('office_id', $locationId)->orWhereNull('office_id'))
            ->where(fn ($query) => $query
                ->where(fn ($q) => $q->whereNotIn('approval_status', ['rejected', 'cancelled'])
                    ->whereNotIn('fulfillment_status', ['issued', 'closed', 'cannot_fulfill']))
                ->orWhere(fn ($q) => $q->whereNotNull('return_requested_at')
                    ->whereDoesntHave('returnDocument', fn ($document) => $document->withoutGlobalScopes()
                        ->where('status', 'POSTED')->where('type', 'receipt')->where('location_id', $locationId))));
        $pendingCount = DB::transactionLevel() > 0
            ? $pending->lockForUpdate()->get(['custom_service_requests.id'])->count() : $pending->count();

        if ($pendingCount > 0) {
            return new ClearanceResult(false, __('office_membership::member.rule_requests_active', ['count' => $pendingCount]));
        }

        return new ClearanceResult(true, __('office_membership::member.rule_requests_completed'));
    }
}
