<?php

namespace GovStore\StoreOperations\Listeners;

use App\Models\Actionlog;
use Exception;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Relations\Relation;

class WriteNativeAuditLogs
{
    /**
     * Responsibility: Safely records audit descriptors and user notes.
     */
    public function handle(InventoryMovementCreated $event)
    {
        $movement = $event->movement;

        try {
            $actionlog = new Actionlog;
            $actionlog->item_type = Relation::getMorphedModel($movement->stockable_type) ?? $movement->stockable_type;
            $actionlog->item_id = $movement->stockable_id;
            $actionlog->created_by = $movement->created_by ?? auth()->id();

            // A quantity projection is an update; reserve checkout logs for native assignments.
            $actionlog->action_type = 'update';
            if ($movement->document?->type === 'issue' && $movement->document->issued_to_user_id) {
                $actionlog->target_type = \App\Models\User::class;
                $actionlog->target_id = $movement->document->issued_to_user_id;
            }

            $docNo = $movement->document?->document_number ?? 'SYSTEM';

            // Log readable note explaining context to auditors
            $actionlog->note = 'GovStore Stores Handshake: '.
                               ($movement->movement_type === 'IN' ? 'Inbound Receipt' : 'Outbound Issuance').
                               " [Qty: {$movement->quantity}]. Reference Document: {$docNo}. balance after: {$movement->balance_after}".
                               ($movement->notes ? ' '.$movement->notes : '');

            $actionlog->save();
        } catch (Exception $e) {
            Log::error("Failed to write standard Actionlog for Movement ID: {$movement->id}. Error: {$e->getMessage()}");
            throw $e;
        }
    }
}
