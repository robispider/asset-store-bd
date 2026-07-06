<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Factories\RequestableFactory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class FulfillmentService
{
    /**
     * Executes the physical progressive checkout and updates document state.
     */
    public function issueItems(ServiceRequest $request, User $storekeeper, array $issueQuantities): ServiceRequest
    {
        if (in_array($request->fulfillment_status, ['closed', 'cannot_fulfill'])) {
            throw new Exception("This service request has already been closed.");
        }

        DB::transaction(function () use ($request, $storekeeper, $issueQuantities) {
            $totalLinesCount = $request->items()->where('line_approval_status', 'approved')->count();
            $completedLinesCount = 0;

            foreach ($request->items as $item) {
                // If the line was rejected during approval, skip it
                if ($item->line_approval_status !== 'approved') {
                    continue;
                }

                $qtyToIssue = (int)($issueQuantities[$item->id] ?? 0);

                if ($qtyToIssue > 0) {
                    $remainingToIssue = $item->approved_qty - $item->issued_qty;

                    if ($qtyToIssue > $remainingToIssue) {
                        throw new Exception("You cannot issue more than the remaining approved quantity for item: " . ($item->requested->name ?? 'Item'));
                    }

                    // 1. Handshake with Snipe-IT Adapter: Execute physical database checkout
                    $type = $item->fulfilled_type ?: $item->requested_type;
                    $id = $item->fulfilled_id ?: $item->requested_id;
                    
                    $adapter = RequestableFactory::make($type, $id);
                    $checkoutSuccess = $adapter->checkout(
                        $request->requester, 
                        $storekeeper, 
                        $qtyToIssue, 
                        "Issued via Service Request {$request->request_number}"
                    );

                    if (!$checkoutSuccess) {
                        throw new Exception("Snipe-IT failed to checkout the item: " . ($item->requested->name ?? 'Item'));
                    }

                    // 2. Update line item cumulative quantities
                    $newIssuedQty = $item->issued_qty + $qtyToIssue;
                    $lineFulfillmentStatus = ($newIssuedQty === $item->approved_qty) ? 'issued' : 'partially_issued';

                    $item->update([
                        'issued_qty' => $newIssuedQty,
                        'line_fulfillment_status' => $lineFulfillmentStatus
                    ]);

                    // 3. Log immutable timeline event
                    RequestEvent::create([
                        'request_id' => $request->id,
                        'user_id' => $storekeeper->id,
                        'event_type' => 'item_issued',
                        'details' => [
                            'item' => $item->requested->name ?? 'Item',
                            'issued_qty' => $qtyToIssue,
                            'total_issued' => $newIssuedQty,
                            'approved_qty' => $item->approved_qty
                        ]
                    ]);
                }

                // Check if this line is now fully completed
                if ($item->issued_qty === $item->approved_qty) {
                    $completedLinesCount++;
                }
            }

            // 4. Update Parent Document State
            $finalFulfillmentStatus = 'partially_issued';
            $finalApprovalStatus = $request->approval_status;

            if ($completedLinesCount === $totalLinesCount) {
                // All approved items have been physically issued -> Transaction Closed
                $finalFulfillmentStatus = 'issued';
                $finalApprovalStatus = 'closed';
                $request->closed_at = now();
                
                RequestEvent::create([
                    'request_id' => $request->id,
                    'user_id' => $storekeeper->id,
                    'event_type' => 'closed',
                    'details' => ['message' => 'All items successfully issued. Request closed.']
                ]);
            }

            $request->update([
                'approval_status' => $finalApprovalStatus,
                'fulfillment_status' => $finalFulfillmentStatus
            ]);
        });

        return $request;
    }

    /**
     * Manually terminates and closes any remaining unfulfilled lines in the request.
     */
    public function forceClose(ServiceRequest $request, User $storekeeper, string $reason = null): ServiceRequest
    {
        DB::transaction(function () use ($request, $storekeeper, $reason) {
            foreach ($request->items as $item) {
                if ($item->line_fulfillment_status !== 'issued' && $item->line_approval_status === 'approved') {
                    $item->update(['line_fulfillment_status' => 'cancelled']);
                }
            }

            $request->update([
                'approval_status' => 'closed',
                'fulfillment_status' => 'closed',
                'closed_at' => now()
            ]);

            RequestEvent::create([
                'request_id' => $request->id,
                'user_id' => $storekeeper->id,
                'event_type' => 'closed',
                'details' => ['message' => 'Force closed by storekeeper. Reason: ' . ($reason ?? 'None provided')]
            ]);
        });

        return $request;
    }
}