<?php

namespace GovStore\StoreOperations\Contracts;

interface StockableInterface
{
    /**
     * Get the current projection quantity in Snipe-IT
     */
    public function getCurrentQuantity(): int;

    /**
     * Increment the Snipe-IT projection quantity
     */
    public function incrementQuantity(int $qty): void;

    /**
     * Decrement the Snipe-IT projection quantity
     */
    public function decrementQuantity(int $qty): void;
    
    /**
     * Get the item's display name
     */
    public function getDisplayName(): string;
}
