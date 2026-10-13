<?php

namespace GovStore\CustomRequests\Services;

use App\Models\User;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Eloquent\Builder;

/**
 * The approval and fulfilment queues, shared by their pages and their navigation badges
 * so a badge always counts exactly what the page lists.
 */
class RequestQueues
{
    /** Requests waiting for this user's approval decision in the working office. */
    public function awaitingApproval(User $user): Builder
    {
        $query = ServiceRequest::query()->whereIn('approval_status', ['pending_primary', 'pending_final']);
        if (! $user->isSuperUser()) {
            // Use the working office, including responsibilities granted as cover.
            $query->whereIn('office_id', [app(TenantContext::class)->locationId]);
        }

        $access = app(GovAccess::class);

        return $query->where('requested_by', '!=', $user->id)->whereNotNull('office_id')
            ->where(function ($q) use ($user, $access) {
                $q->whereRaw('1 = 0');
                if ($access->decide($user, 'requests.approve.primary')->allowed) {
                    $q->orWhere('approval_status', 'pending_primary');
                }
                if ($access->decide($user, 'requests.approve.final')->allowed) {
                    $q->orWhere(fn ($final) => $final->where('approval_status', 'pending_final')
                        ->where(fn ($actor) => $actor->whereNull('primary_decided_by')->orWhere('primary_decided_by', '!=', $user->id)));
                }
            });
    }

    /** Approved requests the working office's store still has to issue. */
    public function awaitingFulfilment(User $user): Builder
    {
        $query = ServiceRequest::query()
            ->whereIn('approval_status', ['approved', 'partially_approved'])
            ->whereNotIn('fulfillment_status', ['closed', 'issued']);
        if (! $user->isSuperUser()) {
            $query->whereIn('office_id', [app(TenantContext::class)->locationId]);
        }

        return $query;
    }
}
