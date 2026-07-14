<?php

namespace GovStore\StoreOperations\Contracts;

interface StockIssuingServiceInterface
{
    /**
     * Outbound System Stock Handshake.
     * Generates a SYSTEM_FULFILLMENT Goods Issue document, creates movements, 
     * and projects quantities securely back to Snipe-IT tables.
     *
     * @param array $items Array of ['stockable_type' => string, 'stockable_id' => int, 'quantity' => int]
     * @param int $issuedToUserId Target user ID receiving the goods
     * @param mixed $referenceDocument The Eloquent Model triggering the checkout (e.g., ServiceRequest)
     * @return string The generated Goods Issue Document Number (e.g., GI-2026-000001)
     */
    public function issueSystemStock(array $items, int $issuedToUserId, $referenceDocument): string;
}
