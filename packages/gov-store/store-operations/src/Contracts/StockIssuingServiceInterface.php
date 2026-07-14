<?php

namespace GovStore\StoreOperations\Contracts;

interface StockIssuingServiceInterface
{
    /**
     * Accepts raw polymorphic types from external packages, sanitizes them, 
     * and processes a SYSTEM_FULFILLMENT Goods Issue if the items are ledger-supported.
     *
     * @param array $items Array of ['type' => string, 'id' => int, 'qty' => int, 'line_id' => int]
     * @param int $issuedToUserId
     * @param mixed $referenceDocument
     * @return array Returns an array of successfully issued line_ids mapping to their Goods Issue Document No.
     */
    public function issueSystemStock(array $items, int $issuedToUserId, $referenceDocument): array;
}
