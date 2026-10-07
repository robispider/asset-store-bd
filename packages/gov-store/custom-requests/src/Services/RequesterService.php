<?php

namespace GovStore\CustomRequests\Services;

use App\Models\User;
use GovStore\CustomRequests\Models\Request;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Support\RequestWorkflow;
use Illuminate\Support\Facades\DB;

class RequesterService
{
    public function transition(int $id, User $actor, string $action): Request
    {
        return DB::transaction(function () use ($id, $actor, $action) {
            $request = Request::whereKey($id)->where('requested_by', $actor->id)->lockForUpdate()->firstOrFail();
            app(RequestAccess::class)->check($request, $actor, 'requests.submit');
            if ($action === 'withdraw') {
                abort_unless(in_array($request->approval_status, RequestWorkflow::PENDING) && ! $request->decided_by && ! $request->primary_decided_by
                    && ! $request->items()->where('issued_qty', '>', 0)->exists(), 409);
                $request->items()->update(['line_approval_status' => 'cancelled', 'line_fulfillment_status' => 'cancelled', 'reserved_qty' => 0]);
                $request->update(['approval_status' => 'cancelled', 'fulfillment_status' => 'closed', 'closed_at' => now()]);
                $event = 'cancelled';
            } else {
                abort_unless($action === 'receive' && $request->fulfillment_status === 'issued' && ! $request->received_at, 409);
                $request->update(['received_at' => now()]);
                $event = 'received';
            }
            RequestEvent::create(['request_id' => $request->id, 'user_id' => $actor->id, 'event_type' => $event, 'details' => []]);

            return $request;
        });
    }
}
