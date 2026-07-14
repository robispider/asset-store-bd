<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\StoreOperations\Models\GoodsIssueItem;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Factories\StockableFactory;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Exception;

class SystemGoodsIssueService implements StockIssuingServiceInterface
{
    protected DocumentNumberService $numberService;
    protected TenantContext $tenantContext;

    public function __construct(DocumentNumberService $numberService, TenantContext $tenantContext)
    {
        $this->numberService = $numberService;
        $this->tenantContext = $tenantContext;
    }

    public function issueSystemStock(array $items, int $issuedToUserId, $referenceDocument): string
    {
        return DB::transaction(function () use ($items, $issuedToUserId, $referenceDocument) {
            
            // 1. Pre-validation: Enforce Negative Stock Prevention across all requested items
            foreach ($items as $item) {
                $adapter = StockableFactory::make($item['stockable_type'], $item['stockable_id']);
                $currentQty = $adapter->getCurrentQuantity();

                if ($currentQty < $item['quantity']) {
                    throw new Exception(
                        "Fulfillment Blocked: Insufficient stock for " . $adapter->getDisplayName() . 
                        ". Available: {$currentQty}, Requested: {$item['quantity']}."
                    );
                }
            }

            // 2. Create Goods Issue Header (Type: SYSTEM_FULFILLMENT)
            $issueNo = $this->numberService->generate('GI', 'gov_goods_issues', 'issue_no');
            $goodsIssue = GoodsIssue::create([
                'issue_no' => $issueNo,
                'issue_type' => 'SYSTEM_FULFILLMENT',
                'issued_to_id' => $issuedToUserId,
                'reference_type' => get_class($referenceDocument),
                'reference_id' => $referenceDocument->id,
                'status' => 'SUBMITTED', // Generated directly in SUBMITTED state (locked)
                'company_id' => $this->tenantContext->companyId,
                'location_id' => $this->tenantContext->locationId,
                'created_by' => auth()->id() ?? 1, // Storekeeper context
            ]);

            // 3. Process items, generate lines, movements, and fire projections
            foreach ($items as $item) {
                // Save Document Line Item
                GoodsIssueItem::create([
                    'goods_issue_id' => $goodsIssue->id,
                    'stockable_type' => $item['stockable_type'],
                    'stockable_id' => $item['stockable_id'],
                    'quantity' => $item['quantity'],
                ]);

                // Create Immutable Ledger Entry (OUT)
                $movement = InventoryMovement::create([
                    'stockable_type' => $item['stockable_type'],
                    'stockable_id'   => $item['stockable_id'],
                    'movement_type'  => 'OUT',
                    'quantity'       => $item['quantity'],
                    'document_type'  => get_class($goodsIssue),
                    'document_id'    => $goodsIssue->id,
                    'company_id'     => $this->tenantContext->companyId,
                    'location_id'    => $this->tenantContext->locationId,
                    'created_by'     => auth()->id() ?? 1,
                ]);

                // Dispatch Projection Event -> Decrement Snipe-IT
                event(new InventoryMovementCreated($movement));
            }

            return $issueNo;
        });
    }
}
