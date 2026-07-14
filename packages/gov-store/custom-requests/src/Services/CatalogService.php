<?php

namespace GovStore\CustomRequests\Services;

use App\Models\Asset;
use App\Models\Accessory;
use App\Models\Consumable;
use App\Models\Component;
use App\Models\License;

class CatalogService
{
    /**
     * Retrieves all items available for requesting within the user's active context.
     */
    public function getAvailableItems(): array
    {
        $catalog = [];

        // 1. HARDWARE ASSETS
        $assets = Asset::with('model.category')
            ->where('requestable', 1)
            ->whereNull('assigned_to')
            ->get();

        foreach ($assets as $asset) {
            $catalog[] = [
                'id' => $asset->id,
                'type' => 'Asset',
                'name' => $asset->name ?: ($asset->model->name ?? 'Unknown Asset'),
                'category' => $asset->model->category->name ?? 'Uncategorized',
                'image_url' => $asset->getImageUrl(),
                'available_qty' => 1,
                'created_timestamp' => $asset->created_at ? $asset->created_at->timestamp : time(),
                'details' => [
                    'Asset Tag: ' . $asset->asset_tag,
                    'Model: ' . ($asset->model->name ?? 'N/A')
                ]
            ];
        }

        // 2. ACCESSORIES
        $accessories = Accessory::with('category')->get();
        foreach ($accessories as $acc) {
            $available = $acc->qty - $acc->users_count;
            if ($available > 0) {
                $catalog[] = [
                    'id' => $acc->id,
                    'type' => 'Accessory',
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
                    'type' => 'Consumable',
                    'name' => $con->name,
                    'category' => $con->category->name ?? 'Consumable',
                    'image_url' => $con->getImageUrl(),
                    'available_qty' => $available,
                    'created_timestamp' => $con->created_at ? $con->created_at->timestamp : time(),
                    'details' => [
                        'Total Stock: ' . $con->qty,
                        'Model/Item No: ' . ($con->item_no ?: 'N/A')
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
                    'type' => 'Component',
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
                    'type' => 'License',
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