<?php

namespace GovStore\StoreOperations\Factories;

use GovStore\StoreOperations\Contracts\StockableInterface;
use GovStore\StoreOperations\Adapters\ConsumableAdapter;
use GovStore\StoreOperations\Adapters\AccessoryAdapter;
use GovStore\StoreOperations\Adapters\ComponentAdapter;
use Exception;
use Illuminate\Support\Str;

class StockableFactory
{
    public static function make(string $type, int $id): StockableInterface
    {
        // Normalize class name (e.g., 'App\Models\Consumable' -> 'Consumable')
        $normalizedType = class_basename($type);

        return match ($normalizedType) {
            'Consumable' => new ConsumableAdapter($id),
            'Accessory'  => new AccessoryAdapter($id),
            'Component'  => new ComponentAdapter($id),
            default      => throw new Exception("Unsupported stockable type: {$type}"),
        };
    }
}
