<?php

namespace GovStore\StoreOperations\Enums;

use App\Models\Consumable;
use App\Models\Accessory;
use App\Models\Component;
use App\Models\AssetModel; 
use Exception;

enum StockableType: string
{
    case CONSUMABLE = Consumable::class;
    case ACCESSORY = Accessory::class;
    case COMPONENT = Component::class;
    case ASSET_MODEL = AssetModel::class;
    /**
     * Highly defensive parser mapping all legacy, class, and morph string 
     * variations cleanly to the canonical StockableType Enum.
     *
     * Handles:
     * - 'App\Models\Consumable' (Fully Qualified Class Name)
     * - 'Consumable' (CamelCase Class Basename)
     * - 'consumable' (Lowercase Morph Database Key)
     */
    public static function fromString(string $type): self
    {
        // Extract basename (strips App\Models\ prefix) and lowercase it
        $normalized = strtolower(class_basename($type));

        return match ($normalized) {
            'consumable' => self::CONSUMABLE,
            'accessory'  => self::ACCESSORY,
            'component'  => self::COMPONENT,
            'assetmodel', 'asset_model' => self::ASSET_MODEL,
            default      => throw new Exception("Unsupported stockable type: {$type}"),
        };
    }
}