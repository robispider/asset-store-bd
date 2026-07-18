<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\StoreOperations\Models\GoodsIssueItem;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Factories\StockableFactory;
use GovStore\StoreOperations\Enums\StockableType;
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

    public function issueSystemStock(array $items, int $issuedToUserId, $referenceDocument): array
    {
        $validLedgerItems = [];
        $processedLines = [];

        // 1. Classification & Validation Phase
        foreach ($items as $item) {
            try {
                // Attempt to classify as a counter-based item. If it fails (e.g., Asset), it's ignored by the ledger.
                $canonicalType = StockableType::fromString($item['type']);
                
                $adapter = StockableFactory::make($canonicalType, $item['id']);
                $currentQty = $adapter->getCurrentQuantity();

                if ($currentQty < $item['qty']) {
                    throw new Exception(
                        __('storeops::storeops.insufficient_stock', [
                            'item' => $adapter->getDisplayName(),
                            'available' => $currentQty,
                            'requested' => $item['qty'],
                        ])
                    );
                }

                $validLedgerItems[] = [
                    'enum_type' => $canonicalType,
                    'raw_type'  => $canonicalType->value, // App\Models\Consumable
                    'id'        => $item['id'],
                    'qty'       => $item['qty'],
                    'line_id'   => $item['line_id'],
                ];
            } catch (Exception $e) {
                // Ignore non-stockable items (Assets, Licenses) - they are handled by their native Snipe-IT workflow
                if (!str_contains($e->getMessage(), 'Unsupported stockable type')) {
                    throw $e;
                }
            }
        }

        if (empty($validLedgerItems)) {
            return []; // Nothing to process for the ledger
        }

        // 2. Transaction Phase
        return DB::transaction(function () use ($validLedgerItems, $issuedToUserId, $referenceDocument, &$processedLines) {
            
            $issueNo = $this->numberService->generate('GI', 'gov_goods_issues', 'issue_no');
            
            $goodsIssue = GoodsIssue::create([
                'issue_no' => $issueNo,
                'issue_type' => 'SYSTEM_FULFILLMENT',
                'issued_to_id' => $issuedToUserId,
                'reference_type' => get_class($referenceDocument),
                'reference_id' => $referenceDocument->id,
                'status' => 'SUBMITTED',
                'company_id' => $this->tenantContext->companyId,
                'location_id' => $this->tenantContext->locationId,
                'created_by' => auth()->id() ?? 1,
            ]);

            foreach ($validLedgerItems as $item) {
                GoodsIssueItem::create([
                    'goods_issue_id' => $goodsIssue->id,
                    'stockable_type' => $item['raw_type'],
                    'stockable_id' => $item['id'],
                    'quantity' => $item['qty'],
                ]);

                // Reliable balance calculation
                $latestBalance = InventoryMovement::where('stockable_type', $item['raw_type'])
                    ->where('stockable_id', $item['id'])
                    ->orderBy('created_at', 'desc')
                    ->value('balance_after') ?? 0;

                $movement = InventoryMovement::create([
                    'stockable_type' => $item['raw_type'],
                    'stockable_id'   => $item['id'],
                    'movement_type'  => 'OUT',
                    'quantity'       => $item['qty'],
                    'balance_after'  => $latestBalance - $item['qty'],
                    'document_type'  => get_class($goodsIssue),
                    'document_id'    => $goodsIssue->id,
                    'company_id'     => $this->tenantContext->companyId,
                    'location_id'    => $this->tenantContext->locationId,
                    'created_by'     => auth()->id() ?? 1,
                ]);

                event(new InventoryMovementCreated($movement));

                // Map the request line ID to the generated Goods Issue number for logging
                $processedLines[$item['line_id']] = $issueNo;
            }

            return $processedLines;
        });
    }
}
