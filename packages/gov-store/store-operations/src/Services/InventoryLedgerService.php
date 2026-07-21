<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\InventoryMovement;
// Import the core Eloquent Relation class to map polymorphic keys
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;

class InventoryLedgerService
{
    /**
     * Retrieve chronological movements for a specific stockable entity.
     * Supports both legacy FQCNs and consolidated polymorphic short keys.
     */
    public function getKardexFor(string $modelClass, int $id, int $limit = 100): Collection
    {
        // 1. Resolve the short polymorphic map key (e.g., 'consumable')
        $morphKey = array_search($modelClass, Relation::morphMap()) ?: $modelClass;

        // 2. Query both the class path and the short key to merge legacy & new records
        return InventoryMovement::with(['document', 'creator'])
            ->whereIn('stockable_type', [$modelClass, $morphKey]) // Query BOTH keys
            ->where('stockable_id', $id)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}