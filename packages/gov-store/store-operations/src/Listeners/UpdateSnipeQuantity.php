<?php

namespace GovStore\StoreOperations\Listeners;

use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Factories\StockableFactory;
use Illuminate\Support\Facades\Log;
use Exception;

class UpdateSnipeQuantity
{
    /**
     * Handle the event to project quantities securely into Snipe-IT tables.
     */
    public function handle(InventoryMovementCreated $event)
    {
        $movement = $event->movement;

        try {
            $adapter = StockableFactory::make($movement->stockable_type, $movement->stockable_id);

            if ($movement->movement_type === 'IN') {
                $adapter->incrementQuantity($movement->quantity);
            } elseif ($movement->movement_type === 'OUT') {
                $adapter->decrementQuantity($movement->quantity);
            }
            
        } catch (Exception $e) {
            // Log severe projection failure (Auditors will rely on movements, but Snipe UI will be out of sync)
            Log::critical("Projection Engine Failure: Could not update Snipe-IT quantity for Movement ID: {$movement->id}. Error: {$e->getMessage()}");
            throw $e; // Re-throw to rollback the database transaction in the Service
        }
    }
}
