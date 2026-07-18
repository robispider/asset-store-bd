<?php

namespace GovStore\CustomRequests\Factories;

use GovStore\CustomRequests\Adapters\AssetAdapter;
use GovStore\CustomRequests\Adapters\AccessoryAdapter;
use GovStore\CustomRequests\Adapters\ConsumableAdapter;
use App\Models\Asset;
use App\Models\Accessory;
use App\Models\Consumable;
use Exception;

class RequestableFactory
{
    /**
     * Pass in a type (e.g., 'Consumable') and an ID, and it returns the proper Adapter ready to go!
     */
    public static function make(string $type, int $id)
    {
        // Normalize the string in case it comes from a UI form or Database morph
        $type = strtolower(class_basename($type));

        switch ($type) {
            case 'asset':
                return new AssetAdapter(Asset::findOrFail($id));

            case 'accessory':
                return new AccessoryAdapter(Accessory::findOrFail($id));

            case 'consumable':
                return new ConsumableAdapter(Consumable::findOrFail($id));

            default:
                throw new Exception(__('requestlabels::requests.requestablefactory_exception_unsupported_type', ['type' => $type]));
        }
    }
}