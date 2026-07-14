<?php

namespace GovStore\StoreOperations\Listeners;

use GovStore\StoreOperations\Events\InventoryMovementCreated;
use App\Models\Actionlog;
use Illuminate\Support\Facades\Log;
use Exception;

class WriteNativeAuditLogs
{
    /**
     * Responsibility: Safely records audit descriptors and user notes.
     */
    public function handle(InventoryMovementCreated $event)
    {
        $movement = $event->movement;

        try {
            $actionlog = new Actionlog();
            $actionlog->item_type = $movement->stockable_type;
            $actionlog->item_id = $movement->stockable_id;
            $actionlog->user_id = $movement->created_by ?? auth()->id() ?? 1;
            
            // Map movement to readable action types
            $actionlog->action_type = $movement->movement_type === 'IN' ? 'requested' : 'checkout'; 
            
            $docNo = $movement->document ? ($movement->document->receipt_no ?? $movement->document->issue_no ?? $movement->document->adjustment_no) : 'SYSTEM';
            
            // Log readable note explaining context to auditors
            $actionlog->note = "GovStore Stores Handshake: " . 
                               ($movement->movement_type === 'IN' ? 'Inbound Receipt' : 'Outbound Issuance') . 
                               " [Qty: {$movement->quantity}]. Reference Document: {$docNo}. balance after: {$movement->balance_after}";
                               
            $actionlog->save();
        } catch (Exception $e) {
            Log::error("Failed to write standard Actionlog for Movement ID: {$movement->id}. Error: {$e->getMessage()}");
        }
    }
}
