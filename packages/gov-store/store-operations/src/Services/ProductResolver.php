<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Enums\StockableType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ProductResolver
{
    /**
     * Unified search mechanism across all supported stockable entities.
     * Fixed the invalid model hasColumn() method call with standard Laravel Schema facade.
     */
    public function search(string $term = '', ?StockableType $type = null, int $limit = 50): Collection
    {
        $results = collect();
        $typesToSearch = $type ? [$type] : StockableType::cases();

        foreach ($typesToSearch as $stockableType) {
            $modelClass = $stockableType->value;
            
            // Resolve actual class path safely
            if (!class_exists($modelClass)) {
                continue;
            }

            $query = $modelClass::query();

            if (!empty($term)) {
                $query->where(function ($q) use ($term, $modelClass) {
                    $q->where('name', 'LIKE', "%{$term}%");
                    
                    // Instantiate a dummy model to safely read its target table name
                    $modelInstance = new $modelClass;
                    $tableName = $modelInstance->getTable();

                    // Safely check if the database table contains an 'item_no' column
                    if (Schema::hasColumn($tableName, 'item_no')) {
                        $q->orWhere('item_no', 'LIKE', "%{$term}%");
                    }
                });
            }

            $items = $query->limit($limit)->get();

            foreach ($items as $item) {
                $results->push([
                    'type_enum'     => $stockableType,
                    'type_raw'      => $stockableType->value,
                    'type_label'    => class_basename($modelClass),
                    'id'            => $item->id,
                    'name'          => $item->name,
                    'item_no'       => $item->item_no ?? 'N/A',
                    'current_stock' => (int) $item->qty,
                ]);
            }
        }

        // Return unified, globally sorted results
        return $results->sortBy('name')->take($limit)->values();
    }
}