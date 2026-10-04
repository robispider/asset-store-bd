<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class RequestNotices
{
    /** Durable outbox written with the transition. Delivery never runs inside an inventory transaction. */
    public function record(RequestEvent $event): void
    {
        $request = $event->request;
        if (! $request->office_id) {
            return;
        }
        $recipients = [$request->requested_by];
        $routing = app(ApprovalRouting::class);
        if ($event->event_type === 'return_requested') {
            $recipients = array_merge($recipients, $routing->candidates($request->office_id, 'storekeeper'));
        }
        if (in_array($request->approval_status, RequestWorkflow::PENDING)) {
            $recipients = array_merge($recipients, $routing->candidates($request->office_id,
                $request->approval_status === 'pending_final' ? 'final_approver' : 'primary_approver',
                array_filter([$request->requested_by, $request->primary_decided_by])));
        } elseif (in_array($request->approval_status, RequestWorkflow::APPROVED) && in_array($request->fulfillment_status, RequestWorkflow::OPEN_FULFILLMENT)) {
            $recipients = array_merge($recipients, $routing->candidates($request->office_id, 'storekeeper'));
        }
        if ($event->event_type === 'escalated' || (in_array($request->approval_status, RequestWorkflow::PENDING) && ! $request->assigned_approver_id)) {
            $recipients = array_merge($recipients, DB::table('gov_location_profiles')->where('location_id', $request->office_id)->pluck('office_admin_id')->all());
        }
        foreach (array_unique(array_filter($recipients)) as $userId) {
            if ((int) $userId === (int) $event->user_id && $event->event_type !== 'escalated') {
                continue;
            }
            DB::table('custom_request_notices')->insertOrIgnore(['request_id' => $request->id, 'user_id' => $userId,
                'event_key' => $event->event_type, 'deduplication_key' => $event->id.':'.$userId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function forUser(int $userId)
    {
        return DB::table('custom_request_notices as notices')->join('custom_service_requests as requests', 'requests.id', '=', 'notices.request_id')
            ->where('notices.user_id', $userId)->where('requests.office_id', app(TenantContext::class)->locationId)
            ->whereNull('requests.deleted_at')->orderByDesc('notices.id')->limit(20)
            ->get(['notices.event_key', 'requests.request_number']);
    }
}
