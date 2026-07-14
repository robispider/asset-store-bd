<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Factories\RequestableFactory;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class FulfillmentService
{
    protected StockIssuingServiceInterface $stockIssuer;

    // Inject the ledger contract cleanly
    public function __construct(StockIssuingServiceInterface $stockIssuer)
    {
        $this->stockIssuer = $stockIssuer;
    }

    public function issueItems(ServiceRequest $request, User $storekeeper, array $issueQuantities, array $substitutions = []): ServiceRequest
    {
        if (in_array($request->fulfillment_status, ['closed', 'cannot_fulfill'])) {
            throw new Exception("This service request has already been closed.");
        }

        DB::transaction(function () use ($request, $storekeeper, $issueQuantities, $substitutions) {
            $totalLinesCount = $request->items()->where('line_approval_status', 'approved')->count();
            $completedLinesCount = 0;

            // Temporary array to hold counter-based items for batch ledger submission
            $ledgerItems = [];

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
                        'fulfilled_type' => $item->requested_type,
                        'fulfilled_id' => $subId
                    ]);

                    // Log substitution to request timeline
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

                    // Resolve target polymorphic identity
                    $type = $item->fulfilled_type ?: $item->requested_type;
                    $id = $item->fulfilled_id ?: $item->requested_id;

                    // Normalize type for classification
                    $classBasename = class_basename($type);

                    if (in_array(strtolower($classBasename), ['consumable', 'accessory', 'component'])) {
                        
                        // COUNTER-BASED ITEMS: Add to batch ledger payload (processed after database lock)
                        $ledgerItems[] = [
                            'stockable_type' => $type,
                            'stockable_id' => $id,
                            'quantity' => $qtyToIssue,
                            'line_item_id' => $item->id,
                            'name' => isset($subName) ? $subName : ($item->requested->name ?? 'Item')
                        ];

                    } else {
                        // SERIALIZED ASSETS & LICENSES: Standard Checkout Adaption Flow
                        $adapter = RequestableFactory::make($type, $id);
                        $checkoutSuccess = $adapter->checkout(
                            $request->requester, 
                            $storekeeper, 
                            $qtyToIssue, 
                            "Issued via Service Request {$request->request_number}"
                        );

                        if (!$checkoutSuccess) {
                            throw new Exception("Snipe-IT failed to checkout the asset item.");
                        }

                        $newIssuedQty = $item->issued_qty + $qtyToIssue;
                        $lineFulfillmentStatus = ($newIssuedQty === $item->approved_qty) ? 'issued' : 'partially_issued';

                        $item->update([
                            'issued_qty' => $newIssuedQty,
                            'line_fulfillment_status' => $lineFulfillmentStatus
                        ]);

                        RequestEvent::create([
                            'request_id' => $request->id,
                            'user_id' => $storekeeper->id,
                            'event_type' => 'item_issued',
                            'details' => [
                                'item' => isset($subName) ? $subName : ($item->requested->name ?? 'Asset'),
                                'issued_qty' => $qtyToIssue,
                                'total_issued' => $newIssuedQty,
                                'approved_qty' => $item->approved_qty
                            ]
                        ]);
                    }
                }

                if ($item->issued_qty === $item->approved_qty) {
                    $completedLinesCount++;
                }
            }

            // 2. Handshake with Ledger: Process counter-based batch items through the contract
            if (!empty($ledgerItems)) {
                // Format payload matching StockIssuingServiceInterface requirements
                $formattedItems = array_map(function ($item) {
                    return [
                        'stockable_type' => $item['stockable_type'],
                        'stockable_id' => $item['stockable_id'],
                        'quantity' => $item['quantity']
                    ];
                }, $ledgerItems);

                // This handles all stock checks, ledger writes, and Snipe-IT updates atomatically
                $giNo = $this->stockIssuer->issueSystemStock($formattedItems, $request->requested_by, $request);

                // Update request line item records
                foreach ($ledgerItems as $ledgerItem) {
                    $itemModel = RequestItem::find($ledgerItem['line_item_id']);
                    $newIssuedQty = $itemModel->issued_qty + $ledgerItem['quantity'];
                    
                    $itemModel->update([
                        'issued_qty' => $newIssuedQty,
                        'line_fulfillment_status' => ($newIssuedQty === $itemModel->approved_qty) ? 'issued' : 'partially_issued'
                    ]);

                    // Log individual line checkout with formal document reference
                    RequestEvent::create([
                        'request_id' => $request->id,
                        'user_id' => $storekeeper->id,
                        'event_type' => 'item_issued',
                        'details' => [
                            'item' => $ledgerItem['name'],
                            'issued_qty' => $ledgerItem['quantity'],
                            'total_issued' => $newIssuedQty,
                            'approved_qty' => $itemModel->approved_qty,
                            'message' => "Logged in Goods Issue: {$giNo}"
                        ]
                    ]);

                    if ($newIssuedQty === $itemModel->approved_qty) {
                        $completedLinesCount++;
                    }
                }
            }

            // 3. Update Parent Document State
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