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
    public function issueItems(ServiceRequest $request, User $storekeeper, array $issueQuantities, array $substitutions = []): ServiceRequest
    {
        if (in_array($request->fulfillment_status, ['closed', 'cannot_fulfill'])) {
            throw new Exception("This service request has already been closed.");
        }

        DB::transaction(function () use ($request, $storekeeper, $issueQuantities, $substitutions) {
        $this->assertSeparationOfDuties($request, $storekeeper);

            $totalLinesCount = $request->items()->where('line_approval_status', 'approved')->count();
            $completedLinesCount = 0;

            foreach ($request->items as $item) {
                if ($item->line_approval_status !== 'approved') {
                    continue;
                }

                // 1. Process Product Substitution if submitted
                $subId = $substitutions[$item->id] ?? null;
                if ($subId && (int)$subId !== (int)$item->requested_id) {
                    $altAdapter = RequestableFactory::make($item->requested_type, $subId);
                    $origName = $item->requested->name ?? $item->requested->asset_tag ?? 'Original Item';
                    $subName = $altAdapter->getDisplayName();

                    $item->update([
                        'fulfilled_type' => $item->requested_type, // Must remain same general type
                        'fulfilled_id' => $subId
                    ]);

                    // Log substitution to the timeline
                    RequestEvent::create([
                        'request_id' => $request->id,
                        'user_id' => $storekeeper->id,
                        'event_type' => 'item_substituted',
                        'details' => [
                            'original' => $origName,
                            'substituted_with' => $subName
                        ]
                    ]);
                }

                $qtyToIssue = (int)($issueQuantities[$item->id] ?? 0);

                if ($qtyToIssue > 0) {
                    $remainingToIssue = $item->approved_qty - $item->issued_qty;

                    if ($qtyToIssue > $remainingToIssue) {
                        throw new Exception("You cannot issue more than the remaining approved quantity.");
                    }

                    // 2. Resolve target polymorphic identity (Use fulfilled/substituted item if set, else requested)
                    $type = $item->fulfilled_type ?: $item->requested_type;
                    $id = $item->fulfilled_id ?: $item->requested_id;
                    
                    // 3. Handshake with Snipe-IT: Check out alternative product
                    $adapter = RequestableFactory::make($type, $id);
                    $checkoutSuccess = $adapter->checkout(
                        $request->requester, 
                        $storekeeper, 
                        $qtyToIssue, 
                        "Issued via Service Request {$request->request_number} (Substitution)"
                    );

                    if (!$checkoutSuccess) {
                        throw new Exception("Snipe-IT failed to checkout the item.");
                    }

                    $newIssuedQty = $item->issued_qty + $qtyToIssue;
                    $lineFulfillmentStatus = ($newIssuedQty === $item->approved_qty) ? 'issued' : 'partially_issued';

                    $item->update([
                        'issued_qty' => $newIssuedQty,
                        'line_fulfillment_status' => $lineFulfillmentStatus
                    ]);

                    // 4. Log cumulative physical issuance
                    $finalName = isset($subName) ? $subName : ($item->requested->name ?? 'Item');
                    RequestEvent::create([
                        'request_id' => $request->id,
                        'user_id' => $storekeeper->id,
                        'event_type' => 'item_issued',
                        'details' => [
                            'item' => $finalName,
                            'issued_qty' => $qtyToIssue,
                            'total_issued' => $newIssuedQty,
                            'approved_qty' => $item->approved_qty
                        ]
                    ]);
                }

                if ($item->issued_qty === $item->approved_qty) {
                    $completedLinesCount++;
                }
            }

            // 5. Update Parent Document State
            $finalFulfillmentStatus = 'partially_issued';
            $finalApprovalStatus = $request->approval_status;

            if ($completedLinesCount === $totalLinesCount) {
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
    public function forceClose(ServiceRequest $request, User $storekeeper, ?string $reason = null): ServiceRequest
    {
        $this->assertSeparationOfDuties($request, $storekeeper);

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

    /**
     * Separation of duties: the administrator who approved a request may not also
     * fulfill (issue or force-close) it, unless they are a super-user. This prevents a
     * single non-super admin from both authorising and physically releasing the same
     * goods. To relax this control, remove this guard or grant the actor super-user rights.
     */
    protected function assertSeparationOfDuties(ServiceRequest $request, User $storekeeper): void
    {
        if (
            $request->approved_by
            && (int) $request->approved_by === (int) $storekeeper->id
            && ! $storekeeper->isSuperUser()
        ) {
            throw new Exception("Separation of duties: the administrator who approved this request cannot also fulfill it. A different storekeeper must issue the items.");
        }
    }
}