<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Factories\RequestableFactory;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Enums\StockableType;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Exception;

class FulfillmentService
{
    protected StockIssuingServiceInterface $stockIssuer;

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

            $issuePayload = [];

            foreach ($request->items as $item) {
                if ($item->line_approval_status !== 'approved') continue;

                // 1. Process Product Substitution
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

                $qtyToIssue = (int)($issueQuantities[$item->id] ?? 0);

                if ($qtyToIssue > 0) {
                    $remainingToIssue = $item->approved_qty - $item->issued_qty;
                    if ($qtyToIssue > $remainingToIssue) throw new Exception("You cannot issue more than the remaining approved quantity.");

                    $type = $item->fulfilled_type ?: $item->requested_type;
                    $id = $item->fulfilled_id ?: $item->requested_id;

                    $issuePayload[] = [
                        'type' => $type,
                        'id' => $id,
                        'qty' => $qtyToIssue,
                        'line_id' => $item->id,
                    ];
                }
            }

            if (empty($issuePayload)) return;

            // 2. ORCHESTRATION PHASE A: The Ledger Handshake
            // Hand off the entire payload. Store Operations will safely ignore Assets/Licenses 
            // and process the counter-based items into the immutable ledger.
            $processedLedgerLines = $this->stockIssuer->issueSystemStock($issuePayload, $request->requested_by, $request);

            // 3. ORCHESTRATION PHASE B: Custody Assignment & Status Updates
            foreach ($issuePayload as $payloadItem) {
                $itemModel = RequestItem::find($payloadItem['line_id']);
                $newIssuedQty = $itemModel->issued_qty + $payloadItem['qty'];
                $message = "Issued via Service Request {$request->request_number}";

                if (isset($processedLedgerLines[$itemModel->id])) {
                    // Item was successfully processed by the ledger. 
                    // Update audit message to include formal Goods Issue document reference.
                    $message = "Logged in Goods Issue: " . $processedLedgerLines[$itemModel->id];
                    
                    // Proceed with Native Snipe-IT Custody Assignment
                    // Resolves the legacy adapter to safely trigger ActionLogs, history, and pivot attachment.
                    $adapter = RequestableFactory::make($payloadItem['type'], $payloadItem['id']);
                    $adapter->checkout($request->requester, $storekeeper, $payloadItem['qty'], $message);

                } else {
                    // It's a Hardware Asset/License (ignored by the ledger). Checkout normally.
                    $adapter = RequestableFactory::make($payloadItem['type'], $payloadItem['id']);
                    $adapter->checkout($request->requester, $storekeeper, $payloadItem['qty'], $message);
                }

                $itemModel->update([
                    'issued_qty' => $newIssuedQty,
                    'line_fulfillment_status' => ($newIssuedQty === $itemModel->approved_qty) ? 'issued' : 'partially_issued'
                ]);

                RequestEvent::create([
                    'request_id' => $request->id,
                    'user_id' => $storekeeper->id,
                    'event_type' => 'item_issued',
                    'details' => [
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

            // 4. Update Parent Document
            $request->update([
                'approval_status' => ($completedLinesCount === $totalLinesCount) ? 'closed' : $request->approval_status,
                'fulfillment_status' => ($completedLinesCount === $totalLinesCount) ? 'issued' : 'partially_issued',
                'closed_at' => ($completedLinesCount === $totalLinesCount) ? now() : null,
            ]);

            if ($completedLinesCount === $totalLinesCount) {
                RequestEvent::create([
                    'request_id' => $request->id,
                    'user_id' => $storekeeper->id,
                    'event_type' => 'closed',
                    'details' => ['message' => 'All items successfully issued. Request closed.']
                ]);
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