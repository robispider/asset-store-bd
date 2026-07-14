<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Models\InventoryMovement;
use Illuminate\Support\Collection;

class InventoryLedgerService
{
    /**
     * Retrieve chronological movements for a specific stockable entity.
     */
    public function getKardexFor(string $modelClass, int $id, int $limit = 100): Collection
    {
        return InventoryMovement::with(['document', 'creator'])
            ->where('stockable_type', $modelClass)
            ->where('stockable_id', $id)
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}
