<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Contracts\Capabilities\CapabilityInterface;
use Exception;

class CapabilityRegistry
{
    /**
     * The master registry mapping string codes to concrete plugin classes.
     */
    protected static array $registry = [
        'require_quantity' => \GovStore\StoreOperations\Capabilities\RequireQuantityCapability::class,
        'require_serial'   => \GovStore\StoreOperations\Capabilities\RequireSerialCapability::class,
        'require_warranty' => \GovStore\StoreOperations\Capabilities\RequireWarrantyCapability::class,
        'post_inventory'   => \GovStore\StoreOperations\Capabilities\PostInventoryCapability::class,
        'create_assets'    => \GovStore\StoreOperations\Capabilities\CreateAssetsCapability::class,
    ];

    /**
     * Dictionary metadata for the Administrative UI.
     * Explains the business purpose of each plugin in plain English.
     */
    protected static array $dictionary = [
        'require_quantity' => [
            'name'  => 'Quantity Required',
            'group' => 'Receiving Validation',
            'desc'  => 'Ensures a valid integer quantity is provided before drafting. Applied natively to all products.'
        ],
        'require_serial' => [
            'name'  => 'Require Serial Numbers',
            'group' => 'Identification',
            'desc'  => 'Renders an expanding grid forcing the storekeeper to enter a unique serial number for every individual unit received. Prevents duplicate scans.'
        ],
        'require_warranty' => [
            'name'  => 'Capture Warranty Period',
            'group' => 'Information Requirements',
            'desc'  => 'Prompts for warranty duration (in months) per item. Calculates exact expiration dates upon final asset creation.'
        ],
        'post_inventory' => [
            'name'  => 'Post to Kardex Ledger',
            'group' => 'Inventory Automation',
            'desc'  => 'Executes the mathematical transaction committing the quantities to the immutable double-entry ledger. Symmetrically increases or decreases running stock balances.'
        ],
        'create_assets' => [
            'name'  => 'Create Physical Assets',
            'group' => 'Execution Automation',
            'desc'  => 'Instantiates physical, serialized assets natively inside Snipe-IT. Automatically links generated Asset Tags and Serial Numbers back to the origin Goods Receipt Document.'
        ],
    ];

    public static function make(string $code): CapabilityInterface
    {
        if (!isset(self::$registry[$code])) {
            throw new Exception("Capability code [{$code}] is not registered in the CapabilityRegistry.");
        }

        return app(self::$registry[$code]);
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

    public static function getDictionary(): array
    {
        return self::$dictionary;
    }
}