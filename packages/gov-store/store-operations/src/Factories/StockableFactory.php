<?php

namespace GovStore\StoreOperations\Factories;

use GovStore\StoreOperations\Contracts\StockableInterface;
use GovStore\StoreOperations\Adapters\ConsumableAdapter;
use GovStore\StoreOperations\Adapters\AccessoryAdapter;
use GovStore\StoreOperations\Adapters\ComponentAdapter;
use GovStore\StoreOperations\Enums\StockableType;

class StockableFactory
{
    /**
     * Resolves the correct stockable adapter.
     * Supports both the strict StockableType Enum and raw database strings, 
     * resolving strings dynamically using the Enum parsing engine.
     */
    public static function make(StockableType|string $type, int $id): StockableInterface
    {
        // Dynamically resolve raw strings to the canonical Enum safely using the new fromString method
        $enumType = $type instanceof StockableType 
            ? $type 
            : StockableType::fromString($type);

        return match ($enumType) {
            StockableType::CONSUMABLE => new ConsumableAdapter($id),
            StockableType::ACCESSORY  => new AccessoryAdapter($id),
            StockableType::COMPONENT  => new ComponentAdapter($id),
        };
    }
}