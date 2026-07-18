<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\GoodsIssue;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Factories\StockableFactory;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Exception;

class GoodsIssueService
{
    protected $tenantContext;

    public function __construct(TenantContext $tenantContext)
    {
        $this->tenantContext = $tenantContext;
    }

    /**
     * Submit transitions the issue from Draft to Submitted, generating outbound ledger movements.
     */
    public function submit(GoodsIssue $issue): void
    {
        if ($issue->status === 'SUBMITTED') {
            throw new Exception(__('storeops::storeops.already_processed', ['document' => 'Goods Issue']));
        }

        if ($issue->items()->count() === 0) {
            throw new Exception(__('storeops::storeops.empty_document_error', ['document' => 'Goods Issue']));
        }

        DB::transaction(function () use ($issue) {
            // 1. Pre-validation: Prevent Negative Stock BEFORE writing anything
            foreach ($issue->items as $item) {
                $adapter = StockableFactory::make($item->stockable_type, $item->stockable_id);
                $currentQty = $adapter->getCurrentQuantity();

                if ($currentQty < $item->quantity) {
                    throw new Exception(
                        __('storeops::storeops.insufficient_stock', [
                            'item' => $adapter->getDisplayName(),
                            'available' => $currentQty,
                            'requested' => $item->quantity,
                        ])
                    );
                }
            }

            // 2. Lock the Document
            $issue->update(['status' => 'SUBMITTED']);

            // 3. Generate OUT Movements for each item
            foreach ($issue->items as $item) {
                $movement = InventoryMovement::create([
                    'stockable_type' => $item->stockable_type,
                    'stockable_id'   => $item->stockable_id,
                    'movement_type'  => 'OUT',
                    'quantity'       => $item->quantity, // Stored as absolute integer
                    'document_type'  => get_class($issue),
                    'document_id'    => $issue->id,
                    'company_id'     => $this->tenantContext->companyId,
                    'location_id'    => $this->tenantContext->locationId,
                    'created_by'     => auth()->id() ?? 1,
                ]);

                // 4. Fire Event for Projection Engine (Decrements Snipe-IT Qty)
                event(new InventoryMovementCreated($movement));
            }
        });
    }
}
