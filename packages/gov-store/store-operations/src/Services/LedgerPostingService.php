<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use Exception;

class LedgerPostingService
{
    /**
     * The exclusive gateway for writing to the immutable ledger.
     * Enforces mathematical symmetry and now supports nullable tenant scoping.
     */
    public function postMovement(
        string $stockableType,
        int $stockableId,
        string $direction, // 'IN' or 'OUT'
        int $quantity,
        object $document,
        ?int $companyId = null,  // Made nullable
        ?int $locationId = null, // Made nullable
        int $userId = 1
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new Exception("Movement quantity must be greater than zero.");
        }

        if (!in_array($direction, ['IN', 'OUT'])) {
            throw new Exception("Invalid movement direction. Allowed: IN, OUT.");
        }

        // 1. Read latest balance with a pessimistic lock
        $latestBalance = InventoryMovement::where('stockable_type', $stockableType)
            ->where('stockable_id', $stockableId)
            ->lockForUpdate() 
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->value('balance_after') ?? 0;

        // 2. Compute symmetric balance
        $newBalance = $direction === 'IN' 
            ? $latestBalance + $quantity 
            : $latestBalance - $quantity;

        // 3. Domain validation
        if ($direction === 'OUT' && $newBalance < 0) {
            throw new Exception("Ledger violation: Insufficient stock for {$stockableType} (ID: {$stockableId}).");
        }

        // 4. Persist the immutable entry
        $movement = InventoryMovement::create([
            'stockable_type' => $stockableType,
            'stockable_id'   => $stockableId,
            'movement_type'  => $direction,
            'quantity'       => $quantity,
            'balance_after'  => $newBalance,
            'document_type'  => get_class($document),
            'document_id'    => $document->id,
            'company_id'     => $companyId,
            'location_id'    => $locationId,
            'created_by'     => $userId,
        ]);

        // 5. Fire event for projection engine
        event(new InventoryMovementCreated($movement));

        return $movement;
    }
}