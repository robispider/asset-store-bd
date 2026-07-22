<?php

namespace GovStore\CustomRequests\Listeners;

use GovStore\CustomRequests\Events\ItemApproved;
use GovStore\CustomRequests\Factories\RequestableFactory;
use Illuminate\Support\Facades\Log;

class ProcessItemCheckout
{
    /**
     * Handle the auto-checkout event.
     * Safely reads dynamic quantities, resolving standard asset serials to 1.
     */
    public function handle(ItemApproved $event)
    {
        $request = $event->itemRequest;

        try {
            // Use our Phase 2 factory to grab the right Snipe-IT adapter
            $adapter = RequestableFactory::make($request->requestable_type, $request->requestable_id);
            
            // Resolve correct checkout quantity (Defaults to 1 for assets or legacy rows)
            $qty = isset($request->quantity) ? (int)$request->quantity : (isset($request->requested_qty) ? (int)$request->requested_qty : 1);

            // Trigger Snipe-IT's core checkout logic with the exact approved quantity!
            $success = $adapter->checkout(
                $request->requester, 
                $event->adminUser, 
                $qty, 
                "Approved via Gov-Store workflow (Request ID: {$request->id})"
            );

            if (!$success) {
                Log::error("Gov-Store: Failed to checkout item for Request ID {$request->id}");
            }

        } catch (\Exception $e) {
            Log::error("Gov-Store: Error processing checkout for Request ID {$request->id} - " . $e->getMessage());
        }
    }
}