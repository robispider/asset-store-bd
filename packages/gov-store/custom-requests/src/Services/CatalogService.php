<?php

namespace GovStore\CustomRequests\Services;

use App\Models\AssetModel;
use App\Models\Accessory;
use App\Models\Consumable;
use App\Models\Component;
use App\Models\License;

class CatalogService
{
    /**
     * Retrieves all items available for requesting within the user's active context.
     * Hardware now queries AssetModels (Templates) rather than serialized Assets.
     */
    public function getAvailableItems(): array
    {
        $catalog = [];

        // 1. HARDWARE ASSET MODELS (The Template Shift)
        // Only load models that actually have unassigned, requestable physical assets in inventory
        $models = AssetModel::with('category')->get();

        foreach ($models as $model) {
            // Dynamically calculate available quantity (unassigned, deployable assets)
            $availableQty = \App\Models\Asset::where('model_id', $model->id)
                ->whereNull('assigned_to')
                ->where('requestable', 1)
                ->count();

            if ($availableQty > 0) {
                $catalog[] = [
                    'id' => $model->id,
                    'type' => 'asset_model', // Polymorphic MorphMap Key
                    'name' => $model->name,
                    'category' => $model->category->name ?? 'Hardware',
                    'image_url' => $model->getImageUrl(),
                    'available_qty' => $availableQty,
                    'created_timestamp' => $model->created_at ? $model->created_at->timestamp : time(),
                    'details' => [
                        'Manufacturer: ' . ($model->manufacturer->name ?? 'N/A'),
                        'Model No: ' . ($model->model_number ?: 'N/A')
                    ]
                ];
            }
        }

        // 2. ACCESSORIES
        $accessories = Accessory::with('category')->get();
        foreach ($accessories as $acc) {
            $available = $acc->qty - $acc->users_count;
            if ($available > 0) {
                $catalog[] = [
                    'id' => $acc->id,
                    'type' => 'accessory',
                    'name' => $acc->name,
                    'category' => $acc->category->name ?? 'Accessory',
                    'image_url' => $acc->getImageUrl(),
                    'available_qty' => $available,
                    'created_timestamp' => $acc->created_at ? $acc->created_at->timestamp : time(),
                    'details' => [
                        'Total Stock: ' . $acc->qty,
                        'Min Amount: ' . $acc->min_amt
                    ]
                ];
            }
        }

        // 3. CONSUMABLES
        $consumables = Consumable::with('category')->get();
        foreach ($consumables as $con) {
            $available = $con->qty - $con->users_count;
            if ($available > 0) {
                $catalog[] = [
                    'id' => $con->id,
                    'type' => 'consumable',
                    'name' => $con->name,
                    'category' => $con->category->name ?? 'Consumable',
                    'image_url' => $con->getImageUrl(),
                    'available_qty' => $available,
                    'created_timestamp' => $con->created_at ? $con->created_at->timestamp : time(),
                    'details' => [
                        'Total Stock: ' . $con->qty,
                        'Item No: ' . ($con->item_no ?: 'N/A')
                    ]
                ];
            }
        }

        // 4. COMPONENTS
        $components = Component::with('category')->get();
        foreach ($components as $comp) {
            $available = $comp->qty - $comp->assets_count;
            if ($available > 0) {
                $catalog[] = [
                    'id' => $comp->id,
                    'type' => 'component',
                    'name' => $comp->name,
                    'category' => $comp->category->name ?? 'Component',
                    'image_url' => $comp->getImageUrl(),
                    'available_qty' => $available,
                    'created_timestamp' => $comp->created_at ? $comp->created_at->timestamp : time(),
                    'details' => [
                        'Total Stock: ' . $comp->qty
                    ]
                ];
            }
        }

        // 5. LICENSES
        $licenses = License::with('category')->get();
        foreach ($licenses as $lic) {
            $available = $lic->seats - $lic->assigned_seats_count;
            if ($available > 0) {
                $catalog[] = [
                    'id' => $lic->id,
                    'type' => 'license',
                    'name' => $lic->name,
                    'category' => $lic->category->name ?? 'Software License',
                    'image_url' => null, 
                    'available_qty' => $available,
                    'created_timestamp' => $lic->created_at ? $lic->created_at->timestamp : time(),
                    'details' => [
                        'Total Seats: ' . $lic->seats,
                        'Expiration: ' . ($lic->expiration_date ?? 'Perpetual')
                    ]
                ];
            }
        }

        // Sort alphabetically by name
        usort($catalog, function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        // Cast to Objects for Blade syntax compatibility
        return array_map(function($item) {
            return (object) $item;
        }, $catalog);
    }
}