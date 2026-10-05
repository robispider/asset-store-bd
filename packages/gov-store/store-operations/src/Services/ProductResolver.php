<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Enums\StockableType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use GovStore\TenantScope\Contexts\TenantContext;

class ProductResolver
{
    private static array $columnsByTable = [];

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

                    $columns = self::$columnsByTable[$tableName] ??= Schema::getColumnListing($tableName);
                    if (in_array('item_no', $columns, true)) {
                        $q->orWhere('item_no', 'LIKE', "%{$term}%");
                    }
                    if (in_array('model_number', $columns, true)) {
                        $q->orWhere('model_number', 'LIKE', "%{$term}%");
                    }
                });
            }

            $items = $query->limit($limit)->get();

            $assetCounts = [];
            if ($stockableType === StockableType::ASSET_MODEL && $items->isNotEmpty()) {
                $officeId = app(TenantContext::class)->locationId;
                $assetCounts = DB::table('assets')->join('status_labels', 'status_labels.id', '=', 'assets.status_id')
                    ->whereIn('assets.model_id', $items->pluck('id'))
                    ->where('assets.location_id', $officeId)->whereNull('assets.deleted_at')
                    ->whereNull('assets.assigned_to')->where('status_labels.deployable', 1)
                    ->where('status_labels.archived', 0)
                    ->selectRaw('assets.model_id, COUNT(*) as stock_count')->groupBy('assets.model_id')
                    ->pluck('stock_count', 'assets.model_id')->all();
            }

            foreach ($items as $item) {
                // DYNAMIC CURRENT STOCK CALCULATION:
                // Consumables/Accessories have 'qty' column. 
                // AssetModels must count active rows in the core 'assets' table.
                $currentStock = 0;
                if (isset($item->qty)) {
                    $currentStock = (int) $item->qty;
                } elseif (class_basename($modelClass) === 'AssetModel') {
                    $currentStock = (int) ($assetCounts[$item->id] ?? 0);
                }

                $results->push([
                    'type_enum'     => $stockableType,
                    'type_raw'      => $stockableType->value,
                    'type_label'    => class_basename($modelClass) === 'AssetModel' ? 'Asset Model' : class_basename($modelClass),
                    'id'            => $item->id,
                    'name'          => $item->name,
                    'item_no'       => $item->item_no ?? $item->model_number ?? 'N/A',
                    'current_stock' => $currentStock,
                    'category_id'   => $item->category_id ?? null,
                ]);
            }
        }

        // Return unified, globally sorted results
        return $results->sortBy('name')->take($limit)->values();
    }
}
