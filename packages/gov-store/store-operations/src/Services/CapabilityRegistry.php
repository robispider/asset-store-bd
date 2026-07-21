<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Contracts\CapabilityInterface;
use Exception;

class CapabilityRegistry
{
    /**
     * Maps simple string keys to concrete Capability classes.
     * Seeding DB tables only requires using these keys.
     */
    protected static array $registry = [
        'require_quantity' => \GovStore\StoreOperations\Capabilities\RequireQuantityCapability::class,
        'require_serial'   => \GovStore\StoreOperations\Capabilities\RequireSerialCapability::class,
        'require_warranty' => \GovStore\StoreOperations\Capabilities\RequireWarrantyCapability::class,
        'post_inventory'   => \GovStore\StoreOperations\Capabilities\PostInventoryCapability::class,
        'create_assets'    => \GovStore\StoreOperations\Capabilities\CreateAssetsCapability::class,
    ];

    /**
     * Instantiates a capability handler by its string code.
     */
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

    /**
     * Retrieve the raw registry array.
     */
    public static function getRegistry(): array
    {
        return self::$registry;
    }
}