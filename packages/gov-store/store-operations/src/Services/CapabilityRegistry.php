<?php

namespace GovStore\StoreOperations\Services;

// FIXED: Corrected namespace import path from singular to match implemented interface
use GovStore\StoreOperations\Contracts\CapabilityInterface;
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
        'require_programme_tracking' => \GovStore\StoreOperations\Capabilities\RequireProgrammeTrackingCapability::class,
        'adjust_inventory' => \GovStore\StoreOperations\Capabilities\AdjustInventoryCapability::class,
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
        'require_programme_tracking' => [
            'name'  => 'Programme Tracking Validation',
            'group' => 'Information Requirements',
            'desc'  => 'Validates GRN items against centralized Programme Tracking goals (e.g. ADP budgets). Blocks unauthorized locations and flags target overages.'
        ],
    ];

    /**
     * Product types a capability is enforced for by default, beneath every profile layer
     * (['issue_govt_tags' => ['assetmodel']]). Profile rules can still DISABLE it at any layer.
     */
    protected static array $defaults = [];

    /**
     * Register a capability from a package provider. The registry stays the single list the
     * engine, the Rules admin and the simulator read.
     */
    public static function extend(string $code, string $class, array $dictionary = [], array $defaultFor = []): void
    {
        if (! is_subclass_of($class, CapabilityInterface::class)) {
            throw new Exception("Capability [{$class}] must implement CapabilityInterface.");
        }
        self::$registry[$code] = $class;
        if ($dictionary) {
            self::$dictionary[$code] = $dictionary;
        }
        if ($defaultFor) {
            self::$defaults[$code] = array_values(array_unique(array_map('strtolower', $defaultFor)));
        }
    }

    /** Default-enforced capability codes for a product type ('assetmodel', 'asset_model' or a class name). */
    public static function defaultsFor(string $productType): array
    {
        $type = strtolower(str_replace('_', '', class_basename($productType)));

        return array_keys(array_filter(self::$defaults, fn ($types) => in_array($type, $types, true)));
    }

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
        $dictionary = self::$dictionary;
        foreach ($dictionary as $code => &$entry) {
            // Package capabilities carry their own translation keys.
            $entry['name'] = __($entry['name_key'] ?? 'storeops::rules.'.$code.'_name');
            $entry['desc'] = __($entry['desc_key'] ?? 'storeops::rules.'.$code.'_desc');
        }
        return $dictionary;
    }
}
