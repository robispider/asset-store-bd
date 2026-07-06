<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Models\RequestEvent;
use Illuminate\Support\Facades\DB;
use Exception;

class BasketService
{
    /**
     * Gets the user's current draft basket, or creates a new one.
     */
    public function getOrCreateDraftBasket($userId): Request
    {
        $basket = Request::firstOrCreate(
            ['requested_by' => $userId, 'approval_status' => 'draft'],
            [
                'request_type' => 'other',
                'purpose' => 'Draft Service Request',
                'justification' => 'Pending submission...',
                'fulfillment_status' => 'unstarted'
            ]
        );

        if ($basket->wasRecentlyCreated) {
            RequestEvent::create([
                'request_id' => $basket->id,
                'user_id' => $userId,
                'event_type' => 'draft_created',
                'details' => ['message' => 'New draft basket initialized']
            ]);
        }

        return $basket;
    }

    /**
     * Adds an item to the draft basket.
     */
    public function addItem($userId, $type, $id, $qty = 1): Request
    {
        $basket = $this->getOrCreateDraftBasket($userId);
        $cleanType = strtolower($type);

        $item = RequestItem::where('request_id', $basket->id)
            ->where('requested_type', $cleanType)
            ->where('requested_id', $id)
            ->first();

        if ($item) {
            // Assets are unique physical items; never increment above 1
            if ($cleanType !== 'asset') {
                $item->requested_qty += $qty;
                $item->save();
            }
        } else {
            RequestItem::create([
                'request_id' => $basket->id,
                'requested_type' => $cleanType,
                'requested_id' => $id,
                'requested_qty' => ($cleanType === 'asset') ? 1 : $qty,
                'line_approval_status' => 'pending',
                'line_fulfillment_status' => 'unstarted',
            ]);
        }

        return $basket->load('items');
    }

    /**
     * Updates item quantity in the basket.
     */
    public function updateItemQty($userId, $itemId, $newQty): bool
    {
        $basket = $this->getOrCreateDraftBasket($userId);
        $item = RequestItem::where('request_id', $basket->id)->where('id', $itemId)->firstOrFail();

        if ($item->requested_type === 'asset') {
            throw new Exception("Hardware assets can only be requested at a quantity of 1 per line item.");
        }

        if ($newQty <= 0) {
            return $item->delete();
        }

        $item->requested_qty = $newQty;
        return $item->save();
    }

    /**
     * Removes an item from the basket.
     */
    public function removeItem($userId, $itemId): bool
    {
        $basket = $this->getOrCreateDraftBasket($userId);
        return RequestItem::where('request_id', $basket->id)->where('id', $itemId)->delete();
    }

    /**
     * Finalizes the draft into an official submitted Service Request document.
     */
    public function submitBasket($userId, array $metadata): Request
    {
        $basket = $this->getOrCreateDraftBasket($userId);

        if ($basket->items()->count() === 0) {
            throw new Exception("You cannot submit an empty service request basket.");
        }

        DB::transaction(function () use ($basket, $userId, $metadata) {
            // Update parent document metadata and status
            $basket->update([
                'request_type' => $metadata['request_type'],
                'purpose' => $metadata['purpose'],
                'justification' => $metadata['justification'],
                'required_by_date' => $metadata['required_by_date'] ?? null,
                'delivery_location_id' => $metadata['delivery_location_id'] ?? null,
                'cost_center' => $metadata['cost_center'] ?? null,
                'approval_status' => 'submitted',
                'submitted_at' => now(),
            ]);

            // Log immutable timeline event
            RequestEvent::create([
                'request_id' => $basket->id,
                'user_id' => $userId,
                'event_type' => 'submitted',
                'details' => [
                    'item_count' => $basket->items()->count(),
                    'purpose' => $metadata['purpose']
                ]
            ]);
        });

        return $basket;
    }
}