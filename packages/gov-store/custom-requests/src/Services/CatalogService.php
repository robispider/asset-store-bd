<?php

namespace GovStore\CustomRequests\Services;

use App\Models\Asset;
use App\Models\Accessory;
use App\Models\Consumable;
use GovStore\CustomRequests\DTOs\CatalogItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class CatalogService
{
    public function getUnifiedCatalog(): Collection
    {
        $catalog = collect();
        $defaultImage = asset('img/default-sm.png');

        // 1. Fetch Assets (Hardware)
        $assets = Asset::with(['model.category', 'model.manufacturer'])->where('requestable', 1)->whereNull('assigned_to')->get();
        foreach ($assets as $asset) {
            $image = $asset->image ? Storage::disk('public')->url('assets/'.$asset->image) : null;
            if (!$image && $asset->model && $asset->model->image) {
                $image = Storage::disk('public')->url('models/'.$asset->model->image);
            }
            
            $details = [];
            if ($asset->model && $asset->model->model_number) $details[] = "Model: " . $asset->model->model_number;
            if ($asset->model && $asset->model->manufacturer) $details[] = "Brand: " . $asset->model->manufacturer->name;
            if ($asset->asset_tag) $details[] = "Asset Tag: " . $asset->asset_tag;

            $catalog->push(new CatalogItem(
                'Asset', $asset->id, $asset->present()->name() ?: $asset->asset_tag,
                $asset->model && $asset->model->category ? $asset->model->category->name : 'Hardware',
                1, $image ?? $defaultImage, $asset->created_at->timestamp, $details
            ));
        }

        // 2. Fetch Accessories
        $accessories = Accessory::with(['category', 'manufacturer'])->get()->filter(function($item) { return $item->numRemaining() > 0; });
        foreach ($accessories as $acc) {
            $image = $acc->image ? Storage::disk('public')->url('accessories/'.$acc->image) : $defaultImage;
            
            $details = [];
            if ($acc->model_number) $details[] = "Model: " . $acc->model_number;
            if ($acc->manufacturer) $details[] = "Brand: " . $acc->manufacturer->name;
            
            $catalog->push(new CatalogItem(
                'Accessory', $acc->id, $acc->name,
                $acc->category ? $acc->category->name : 'Peripherals',
                $acc->numRemaining(), $image, $acc->created_at->timestamp, $details
            ));
        }

        // 3. Fetch Consumables
        $consumables = Consumable::with(['category', 'manufacturer'])->get()->filter(function($item) { return $item->numRemaining() > 0; });
        foreach ($consumables as $con) {
            $image = $con->image ? Storage::disk('public')->url('consumables/'.$con->image) : $defaultImage;
            
            $details = [];
            if ($con->item_no) $details[] = "Item No: " . $con->item_no;
            if ($con->manufacturer) $details[] = "Brand: " . $con->manufacturer->name;
            if ($con->model_number) $details[] = "Model: " . $con->model_number;

            $catalog->push(new CatalogItem(
                'Consumable', $con->id, $con->name,
                $con->category ? $con->category->name : 'Supplies',
                $con->numRemaining(), $image, $con->created_at->timestamp, $details
            ));
        }

        return $catalog->sortBy('name')->values();
    }
}