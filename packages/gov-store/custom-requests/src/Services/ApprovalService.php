<?php

namespace GovStore\CustomRequests\Services;

use App\Models\User;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Support\RequestWorkflow;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ApprovalService
{
    public function processDecision(ServiceRequest $request, User $admin, array $itemDecisions): ServiceRequest
    {
        return DB::transaction(function () use ($request, $admin, $itemDecisions) {
            $request = ServiceRequest::whereKey($request->id)->lockForUpdate()->firstOrFail();
            abort_unless(in_array($request->approval_status, RequestWorkflow::PENDING), 409);
            $primary = $request->approval_status === 'pending_primary';
            app(RequestAccess::class)->check($request, $admin, $primary ? 'requests.approve.primary' : 'requests.approve.final');
            abort_if((int) $request->requested_by === (int) $admin->id, 403);
            abort_if(! $primary && (int) $request->primary_decided_by === (int) $admin->id, 403);
            abort_if(! $primary && $request->resolved_policy === 'PRIMARY_AND_FINAL' && ! $request->primary_decided_by, 409);
            Validator::make(['items' => $itemDecisions], [
                'items' => 'required|array', 'items.*' => 'required|array',
                'items.*.status' => 'required|in:approved,rejected',
                'items.*.qty' => 'required|integer|min:0|max:'.RequestWorkflow::MAX_QUANTITY,
                'items.*.notes' => 'nullable|string|max:2000',
            ])->validate();
            $lines = $request->items()->orderBy('requested_type')->orderBy('requested_id')->get();
            abort_if(array_diff(array_keys($itemDecisions), $lines->pluck('id')->all()), 422);
            $accepted = 0;
            $rejected = 0;
            foreach ($lines as $line) {
                if (! $primary && $line->line_approval_status === 'rejected') {
                    $rejected++;

                    continue;
                }
                abort_unless(isset($itemDecisions[$line->id]), 422);
                $decision = $itemDecisions[$line->id];
                $approved = $decision['status'] === 'approved';
                $cap = ($primary || ! $request->primary_decided_by) ? $line->requested_qty : min($line->requested_qty, $line->approved_qty);
                $qty = $approved ? min((int) $decision['qty'], $cap) : 0;
                abort_if($approved && $qty < 1, 422);
                $awaitFinal = $primary && $request->resolved_policy === 'PRIMARY_AND_FINAL';
                $line->update([
                    'approved_qty' => $qty, 'reserved_qty' => 0,
                    'line_approval_status' => $approved ? ($awaitFinal ? 'pending' : 'approved') : 'rejected',
                    'line_fulfillment_status' => $approved ? ($awaitFinal ? 'unstarted' : 'waiting') : 'cancelled',
                    'notes' => $decision['notes'] ?? null,
                ]);
                $approved ? $accepted++ : $rejected++;
            }
            $status = $accepted === 0 ? 'rejected' : (($primary && $request->resolved_policy === 'PRIMARY_AND_FINAL')
                ? 'pending_final' : ($rejected > 0 ? 'partially_approved' : 'approved'));
            $complete = in_array($status, RequestWorkflow::APPROVED);
            $request->update([
                'approval_status' => $status, 'fulfillment_status' => $accepted === 0 ? 'closed' : 'unstarted',
                'primary_decided_by' => $primary ? $admin->id : $request->primary_decided_by,
                'decided_by' => $admin->id, 'approved_by' => $complete ? $admin->id : null,
                'approved_at' => $complete ? now() : null, 'closed_at' => $accepted === 0 ? now() : null,
            ]);
            if ($complete) {
                app(RequestInventory::class)->reserve($request);
            } elseif ($status === 'pending_final') {
                app(ApprovalRouting::class)->assign($request);
            }
            RequestEvent::create(['request_id' => $request->id, 'user_id' => $admin->id,
                'event_type' => $status, 'details' => ['stage' => $primary ? 'primary' : 'final', 'decisions' => $itemDecisions]]);

            return $request;
        }, 3);
    }
}
