<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Factories\RequestableFactory;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
// REMOVED UNUSED IMPORT: use GovStore\StoreOperations\Enums\StockableType;
use App\Models\User;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;
use Exception;

class FulfillmentService
{
    protected StockIssuingServiceInterface $stockIssuer;

    public function __construct(StockIssuingServiceInterface $stockIssuer)
    {
        $this->stockIssuer = $stockIssuer;
    }

    public function issueItems(ServiceRequest $request, User $storekeeper, array $issuePayload, array $substitutions = []): ServiceRequest
    {
        if (in_array($request->fulfillment_status, ['closed', 'cannot_fulfill'])) {
            throw new Exception(__('requestlabels::requests.fulfillmentservice_exception_already_closed'));
        }

        DB::transaction(function () use ($request, $storekeeper, $issuePayload, $substitutions) {
            $totalLinesCount = $request->items()->where('line_approval_status', 'approved')->count();
            $completedLinesCount = 0;

            $ledgerPayload = []; // For Consumables, Accessories, Components

            foreach ($request->items as $item) {
                if ($item->line_approval_status !== 'approved') continue;

                // 1. Process Substitution Logic (Unchanged)
                $subId = $substitutions[$item->id] ?? null;
                if ($subId && (int)$subId !== (int)$item->requested_id) {
                    $altAdapter = RequestableFactory::make($item->requested_type, $subId);
                    
                    $item->update([
                        'fulfilled_type' => $item->requested_type,
                        'fulfilled_id' => $subId
                    ]);

                    RequestEvent::create([
                        'request_id' => $request->id,
                        'user_id' => $storekeeper->id,
                        'event_type' => 'item_substituted',
                        'details' => ['original' => 'Original Item', 'substituted_with' => $altAdapter->getDisplayName()]
                    ]);
                }

                $type = strtolower(class_basename($item->fulfilled_type ?: $item->requested_type));
                $isAssetModel = in_array($type, ['assetmodel', 'asset_model']);
                $remainingToIssue = $item->approved_qty - $item->issued_qty;

                // ---------------------------------------------------------
                // SCENARIO A: ASSET MODEL (Array of Specific Asset IDs)
                // ---------------------------------------------------------
                if ($isAssetModel) {
                    $selectedAssetIds = $issuePayload[$item->id] ?? [];
                    // Filter out empty selections from the dropdown array
                    $selectedAssetIds = array_filter($selectedAssetIds); 
                    
                    $qtyToIssue = count($selectedAssetIds);

                    if ($qtyToIssue > 0) {
                        if ($qtyToIssue > $remainingToIssue) {
                            throw new Exception("Cannot issue more assets than approved for line {$item->id}");
                        }

                        $successfullyIssued = 0;

                        // Loop through each physical asset and check it out
                        foreach ($selectedAssetIds as $assetId) {
                            $asset = Asset::find($assetId);
                            if (!$asset) throw new Exception("Asset ID {$assetId} not found.");
                            if ($asset->assigned_to) throw new Exception("Asset {$asset->asset_tag} is already assigned!");

                            // Natively assign the asset to the requester
                            $asset->assigned_to = $request->requested_by;
                            $asset->assigned_type = User::class;
                            $asset->location_id = $request->requester->location_id;
                            
                            if ($asset->save()) {
                                $asset->logCheckout("Issued via Gov-Store Request: {$request->request_number}", $request->requester);
                                $successfullyIssued++;
                            }
                        }

                        // Update Line Item Status
                        $newIssuedQty = $item->issued_qty + $successfullyIssued;
                        $item->update([
                            'issued_qty' => $newIssuedQty,
                            'line_fulfillment_status' => ($newIssuedQty === $item->approved_qty) ? 'issued' : 'partially_issued'
                        ]);

                        RequestEvent::create([
                            'request_id' => $request->id,
                            'user_id' => $storekeeper->id,
                            'event_type' => 'item_issued',
                            'details' => [
                                'item' => 'Hardware Assets',
                                'issued_qty' => $successfullyIssued,
                                'total_issued' => $newIssuedQty,
                                'approved_qty' => $item->approved_qty,
                                'message' => "Successfully issued {$successfullyIssued} physical serialized assets."
                            ]
                        ]);

                        if ($newIssuedQty === $item->approved_qty) {
                            $completedLinesCount++;
                        }
                    }
                } 
                // ---------------------------------------------------------
                // SCENARIO B: BULK ITEMS (Consumables, Accessories)
                // ---------------------------------------------------------
                else {
                    $qtyToIssue = (int)($issuePayload[$item->id] ?? 0);

                    if ($qtyToIssue > 0) {
                        if ($qtyToIssue > $remainingToIssue) {
                            throw new Exception("Cannot issue more items than approved for line {$item->id}");
                        }

                        // Pack into array for the Store Operations Ledger
                        $id = $item->fulfilled_id ?: $item->requested_id;
                        $ledgerPayload[] = [
                            'type' => $type,
                            'id' => $id,
                            'qty' => $qtyToIssue,
                            'line_id' => $item->id,
                        ];
                    }
                }
            }

            // 2. Process Bulk Items via Store Operations Immutable Ledger
            if (!empty($ledgerPayload)) {
                $processedLedgerLines = $this->stockIssuer->issueSystemStock($ledgerPayload, $request->requested_by, $request);

                foreach ($ledgerPayload as $payloadItem) {
                    $itemModel = RequestItem::find($payloadItem['line_id']);
                    $newIssuedQty = $itemModel->issued_qty + $payloadItem['qty'];
                    
                    // The Ledger returns the generated Goods Issue (GI) document number
                    $giNumber = $processedLedgerLines[$itemModel->id] ?? 'Unknown GI Document';
                    $message = "Logged in Goods Issue: " . $giNumber;

                    // Trigger native Snipe-IT history log via adapter
                    $adapter = RequestableFactory::make($payloadItem['type'], $payloadItem['id']);
                    $adapter->checkout($request->requester, $storekeeper, $payloadItem['qty'], $message);

                    $itemModel->update([
                        'issued_qty' => $newIssuedQty,
                        'line_fulfillment_status' => ($newIssuedQty === $itemModel->approved_qty) ? 'issued' : 'partially_issued'
                    ]);

                    RequestEvent::create([
                        'request_id' => $request->id,
                        'user_id' => $storekeeper->id,
                        'event_type' => 'item_issued',
                        'details' => [
                            'item' => $adapter->getDisplayName(),
                            'issued_qty' => $payloadItem['qty'],
                            'total_issued' => $newIssuedQty,
                            'approved_qty' => $itemModel->approved_qty,
                            'message' => $message
                        ]
                    ]);

                    if ($newIssuedQty === $itemModel->approved_qty) {
                        $completedLinesCount++;
                    }
                }
            }

            // 3. Evaluate Parent Document Closure
            if ($completedLinesCount === $totalLinesCount && $totalLinesCount > 0) {
                $request->update([
                    'approval_status' => 'closed',
                    'fulfillment_status' => 'issued',
                    'closed_at' => now(),
                ]);

                RequestEvent::create([
                    'request_id' => $request->id,
                    'user_id' => $storekeeper->id,
                    'event_type' => 'closed',
                    'details' => ['message' => 'All items successfully issued. Request closed.']
                ]);
            } else {
                $totalIssuedAnything = $request->items()->where('issued_qty', '>', 0)->count();
                if ($totalIssuedAnything > 0) {
                    $request->update(['fulfillment_status' => 'partially_issued']);
                }
            }
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