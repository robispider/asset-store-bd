<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use Exception;

class CapabilityRegistry
{
    protected static array $registry = [
        'require_quantity'   => \GovStore\StoreOperations\Capabilities\RequireQuantityCapability::class,
        'require_serial'     => \GovStore\StoreOperations\Capabilities\RequireSerialCapability::class,
        'require_warranty'   => \GovStore\StoreOperations\Capabilities\RequireWarrantyCapability::class,
        'post_inventory'     => \GovStore\StoreOperations\Capabilities\PostInventoryCapability::class,
        'create_assets'      => \GovStore\StoreOperations\Capabilities\CreateAssetsCapability::class,
        // Added Phase 5 Operational Plugins
        'adjust_inventory'   => \GovStore\StoreOperations\Capabilities\AdjustInventoryCapability::class,
        'transfer_inventory' => \GovStore\StoreOperations\Capabilities\TransferInventoryCapability::class,
    ];

    public static function make(string $code): CapabilityInterface
    {
        if (!isset(self::$registry[$code])) {
            throw new Exception("Capability code [{$code}] is not registered in the CapabilityRegistry.");
        }

        $handlerClass = self::$registry[$code];

        if (!class_exists($handlerClass)) {
            throw new Exception("Capability handler class [{$handlerClass}] does not exist.");
        }

        return app($handlerClass);
    }

    public static function getClass(string $code): string
    {
        if (!isset(self::$registry[$code])) {
            throw new Exception("Capability code [{$code}] is not registered.");
        }
        return self::$registry[$code];
    }

    public static function getRegistry(): array
    {
        return self::$registry;
    }
}