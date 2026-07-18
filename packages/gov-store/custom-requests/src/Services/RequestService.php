<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\ItemRequest;
use App\Models\User;
use Exception;

class RequestService
{
    /**
     * Submit a new request for an item.
     * $type should be something like App\Models\Consumable
     */
    public function submitRequest(string $type, int $id, User $requester, string $notes = null): ItemRequest
    {
        // 1. Prevent duplicate pending requests for the same exact item by the same user
        $existing = ItemRequest::where('requestable_type', $type)
            ->where('requestable_id', $id)
            ->where('requested_by', $requester->id)
            ->pending()
            ->first();

        if ($existing) {
            throw new Exception(__('requestlabels::requests.requestservice_exception_duplicate_pending'));
        }

        // 2. Create the request in our custom table
        return ItemRequest::create([
            'requestable_type' => $type,
            'requestable_id'   => $id,
            'requested_by'     => $requester->id,
            'status'           => 'pending',
            'notes'            => $notes,
        ]);
    }
}