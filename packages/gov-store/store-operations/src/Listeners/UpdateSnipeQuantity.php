<?php

namespace GovStore\StoreOperations\Listeners;

use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Factories\StockableFactory;
use Illuminate\Support\Facades\Log;
use Exception;

class UpdateSnipeQuantity
{
    /**
     * Responsibility: Strictly handles the mathematical projection to Snipe-IT tables.
     */
    public function handle(InventoryMovementCreated $event)
    {
        $movement = $event->movement;

        if ($movement->document && $movement->document->type === 'opening') {
            return;
        }
        if (strtolower(class_basename($movement->stockable_type)) === 'assetmodel') {
            return;
        }

        try {
            $adapter = StockableFactory::make($movement->stockable_type, $movement->stockable_id);

            if ($movement->movement_type === 'IN') {
                $adapter->incrementQuantity($movement->quantity);
            } elseif ($movement->movement_type === 'OUT') {
                $adapter->decrementQuantity($movement->quantity);
            }
        } catch (Exception $e) {
            Log::critical("Projection Engine Failure: Could not update Snipe-IT quantity for Movement ID: {$movement->id}. Error: {$e->getMessage()}");
            throw $e;
        }
    }
}
