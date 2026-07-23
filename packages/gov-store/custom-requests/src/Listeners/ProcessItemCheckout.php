<?php

namespace GovStore\CustomRequests\Listeners;

use GovStore\CustomRequests\Events\ItemApproved;
use GovStore\CustomRequests\Factories\RequestableFactory;
use Illuminate\Support\Facades\Log;

class ProcessItemCheckout
{
    /**
     * Handle the auto-checkout event upon approval.
     * Safely bypasses Asset Models (which require manual storekeeper serial assignment),
     * while auto-fulfilling bulk consumables and accessories.
     */
    public function handle(ItemApproved $event)
    {
        $request = $event->itemRequest;
        $type = strtolower(class_basename($request->requestable_type));

        try {
            // 1. Edge Case: Skip Asset Models entirely. 
            // They MUST go to the Fulfillment Queue for physical serial selection.
            if (in_array($type, ['assetmodel', 'asset_model'])) {
                Log::info("Gov-Store: Auto-checkout skipped for Request ID {$request->id}. Asset Models require manual fulfillment.");
                return; // Exit listener gracefully
            }

            // 2. Normal Bulk Processing
            $adapter = RequestableFactory::make($request->requestable_type, $request->requestable_id);
            $qty = isset($request->quantity) ? (int)$request->quantity : (isset($request->requested_qty) ? (int)$request->requested_qty : 1);

            $success = $adapter->checkout(
                $request->requester, 
                $event->adminUser, 
                $qty, 
                "Auto-Approved via Gov-Store workflow (Request ID: {$request->id})"
            );

            if (!$success) {
                Log::error("Gov-Store: Failed to auto-checkout item for Request ID {$request->id}");
            }

        } catch (\Exception $e) {
            Log::error("Gov-Store: Error processing auto-checkout for Request ID {$request->id} - " . $e->getMessage());
        }
    }
}