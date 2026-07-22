<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Enums\StockableType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class ProductResolver
{
    /**
     * Unified search mechanism across Consumables, Accessories, and Asset Models.
     * Dynamically calculates stock levels for Asset Models using active count queries.
     */
    public function search(string $term = '', ?StockableType $type = null, int $limit = 50): Collection
    {
        $results = collect();
        $typesToSearch = $type ? [$type] : StockableType::cases();

        foreach ($typesToSearch as $stockableType) {
            $modelClass = $stockableType->value;
            
            if (!class_exists($modelClass)) {
                continue;
            }

            $query = $modelClass::query();

            if (!empty($term)) {
                $query->where(function ($q) use ($term, $modelClass) {
                    $q->where('name', 'LIKE', "%{$term}%");
                    
                    $modelInstance = new $modelClass;
                    $tableName = $modelInstance->getTable();

                    // Safely check if the database table contains 'item_no' or 'model_number'
                    if (Schema::hasColumn($tableName, 'item_no')) {
                        $q->orWhere('item_no', 'LIKE', "%{$term}%");
                    }
                    if (Schema::hasColumn($tableName, 'model_number')) {
                        $q->orWhere('model_number', 'LIKE', "%{$term}%");
                    }
                });
            }

            $items = $query->limit($limit)->get();

            foreach ($items as $item) {
                // DYNAMIC CURRENT STOCK CALCULATION:
                // Consumables/Accessories have 'qty' column. 
                // AssetModels must count active rows in the core 'assets' table.
                $currentStock = 0;
                if (isset($item->qty)) {
                    $currentStock = (int) $item->qty;
                } elseif (class_basename($modelClass) === 'AssetModel') {
                    // Count actual physical assets registered in Snipe-IT for this model
                    $currentStock = DB::table('assets')
                        ->where('model_id', $item->id)
                        ->whereNull('deleted_at')
                        ->count();
                }

                $results->push([
                    'type_enum'     => $stockableType,
                    'type_raw'      => $stockableType->value,
                    'type_label'    => class_basename($modelClass) === 'AssetModel' ? 'Asset Model' : class_basename($modelClass),
                    'id'            => $item->id,
                    'name'          => $item->name,
                    'item_no'       => $item->item_no ?? $item->model_number ?? 'N/A',
                    'current_stock' => $currentStock,
                ]);
            }
        }

        // Return unified, globally sorted results
        return $results->sortBy('name')->take($limit)->values();
    }
}