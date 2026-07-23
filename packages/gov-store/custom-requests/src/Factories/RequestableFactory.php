<?php

namespace GovStore\CustomRequests\Factories;

use GovStore\CustomRequests\Adapters\AssetAdapter;
use GovStore\CustomRequests\Adapters\AssetModelAdapter;
use GovStore\CustomRequests\Adapters\AccessoryAdapter;
use GovStore\CustomRequests\Adapters\ConsumableAdapter;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Accessory;
use App\Models\Consumable;
use Exception;

class RequestableFactory
{
    /**
     * Instantiates the correct adapter for cross-context display and checkout orchestration.
     */
    public static function make(string $type, int $id)
    {
        $type = strtolower(class_basename($type));

        switch ($type) {
            case 'asset': 
                // Legacy: Handles requests made before the Phase 1 Template Shift
                return new AssetAdapter(Asset::findOrFail($id));

            case 'assetmodel':
            case 'asset_model': 
                // Modern: Handles multi-quantity Template requests
                return new AssetModelAdapter(AssetModel::findOrFail($id));

            case 'accessory':
                return new AccessoryAdapter(Accessory::findOrFail($id));

            case 'consumable':
                return new ConsumableAdapter(Consumable::findOrFail($id));

            default:
                throw new Exception(__('requestlabels::requests.requestablefactory_exception_unsupported_type', ['type' => $type]));
        }
    }
}