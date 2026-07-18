<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\GoodsReceipt;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Exception;

class GoodsReceiptService
{
    protected $numberService;
    protected $tenantContext;

    public function __construct(DocumentNumberService $numberService, TenantContext $tenantContext)
    {
        $this->numberService = $numberService;
        $this->tenantContext = $tenantContext;
    }

    /**
     * Submit transitions the receipt from Draft to Submitted, generating ledger movements.
     */
    public function submit(GoodsReceipt $receipt): void
    {
        if ($receipt->status === 'SUBMITTED') {
            throw new Exception(__('storeops::storeops.already_processed', ['document' => 'Goods Receipt']));
        }

        if ($receipt->items()->count() === 0) {
            throw new Exception(__('storeops::storeops.empty_document_error', ['document' => 'Goods Receipt']));
        }

        DB::transaction(function () use ($receipt) {
            // 1. Lock the Document
            $receipt->update(['status' => 'SUBMITTED']);

            // 2. Generate Movements for each item
            foreach ($receipt->items as $item) {
                // 1. Retrieve latest balance natively
                $latestBalance = InventoryMovement::where('stockable_type', $item->stockable_type)
                    ->where('stockable_id', $item->stockable_id)
                    ->orderBy('created_at', 'desc')
                    ->value('balance_after') ?? 0;

                $newBalance = $latestBalance + $item->quantity;

                // 2. Persist with computed balance_after
                $movement = InventoryMovement::create([
                    'stockable_type' => $item->stockable_type,
                    'stockable_id'   => $item->stockable_id,
                    'movement_type'  => 'IN',
                    'quantity'       => $item->quantity,
                    'balance_after'  => $newBalance,
                    'document_type'  => get_class($receipt),
                    'document_id'    => $receipt->id,
                    'company_id'     => $this->tenantContext->companyId,
                    'location_id'    => $this->tenantContext->locationId,
                    'created_by'     => auth()->id() ?? 1, // Fallback for CLI/System
                ]);

                event(new InventoryMovementCreated($movement));
            }
        });
    }
}
