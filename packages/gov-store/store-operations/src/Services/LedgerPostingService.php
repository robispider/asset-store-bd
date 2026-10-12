<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Enums\StockableType;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
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
        int $userId = 1,
        ?string $notes = null
    ): InventoryMovement {
        return DB::transaction(function () use ($stockableType, $stockableId, $direction, $quantity, $document, $companyId, $locationId, $userId, $notes) {
            if ($quantity <= 0) {
                throw new Exception("Movement quantity must be greater than zero.");
            }

            if (!in_array($direction, ['IN', 'OUT'])) {
                throw new Exception("Invalid movement direction. Allowed: IN, OUT.");
            }

            $locationId ??= app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId;
            if (! $locationId) {
                throw new Exception('A working office is required to post ledger movements.');
            }

            $type = StockableType::fromString($stockableType);
            $modelClass = Relation::getMorphedModel($type->value) ?? $type->value;
            $stockable = $modelClass::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($stockableId)->lockForUpdate()->firstOrFail();
            if ($type !== StockableType::ASSET_MODEL && (int) $stockable->location_id !== $locationId) {
                throw new Exception('The stock item does not belong to the posting office.');
            }
            if (($type !== StockableType::ASSET_MODEL && (int) $stockable->company_id !== (int) $companyId)
                || ($type === StockableType::ASSET_MODEL && $stockable->company_id && (int) $stockable->company_id !== (int) $companyId)) {
                throw new Exception('The stock item does not belong to the posting company.');
            }
            if ((int) $document->location_id !== $locationId || (int) $document->company_id !== (int) $companyId) {
                throw new Exception('The ledger movement must belong to its document office and company.');
            }
            $stockableType = $stockable->getMorphClass();

            if ($document->type !== 'opening' && ! DB::table('gov_store_ledger_openings')->where('location_id', $locationId)->exists()) {
                throw new Exception('Opening stock must be recorded for this office before ledger posting.');
            }

            // Serialize by stock item before reading the office-specific balance.
            $latestBalance = InventoryMovement::withoutGlobalScopes()->where('stockable_type', $stockableType)
                ->where('stockable_id', $stockableId)
                ->where('location_id', $locationId)
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
                'notes' => $notes,
            ]);

            // 5. Fire event for projection engine
            event(new InventoryMovementCreated($movement));

            return $movement;
        });
    }
}
