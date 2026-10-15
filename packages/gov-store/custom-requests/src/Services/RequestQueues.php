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

    /**
     * Waiting requests already passed to the office head (an escalation event since the last change), or
     * waiting longer than the escalation period; the same rule gov-requests:maintain applies.
     */
    public function overdue(User $user): Builder
    {
        $cutoff = now()->subWeekdays(max(1, (int) config('govstore-requests.escalation_weekdays', 3)));

        return $this->awaitingApproval($user)->where(fn ($q) => $q->where('updated_at', '<', $cutoff)
            ->orWhereHas('events', fn ($e) => $e->where('event_type', 'escalated')->whereColumn('custom_service_request_events.created_at', '>=', 'custom_service_requests.updated_at')));
    }

    /** Requests this user decided, at either stage, in the working office. */
    public function decidedBy(User $user): Builder
    {
        $query = ServiceRequest::query()->whereNotIn('approval_status', ['draft', 'submitted', 'under_review'])
            ->where(fn ($q) => $q->where('decided_by', $user->id)->orWhere('primary_decided_by', $user->id));
        if (! $user->isSuperUser()) {
            $query->where('office_id', app(TenantContext::class)->locationId);
        }

        return $query;
    }

    /** Requests the store has issued or closed and the requester wants to return; no receipt drafted yet. */
    public function returnsToReceive(User $user): Builder
    {
        $query = ServiceRequest::query()->whereNotNull('return_requested_at')->whereNull('return_document_id')
            ->whereIn('fulfillment_status', ['issued', 'closed']);
        if (! $user->isSuperUser()) {
            $query->where('office_id', app(TenantContext::class)->locationId);
        }

        return $query;
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
