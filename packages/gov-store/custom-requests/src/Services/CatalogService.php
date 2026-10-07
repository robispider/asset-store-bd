<?php

namespace GovStore\CustomRequests\Services;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Consumable;
use GovStore\CustomRequests\Models\RequestItem;
use GovStore\CustomRequests\Support\RequestWorkflow;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;

class CatalogService
{
    /** Filter and paginate before hydrating inventory models. No per-item count queries. */
    public function paginate(array $filters = [], int $perPage = 24)
    {
        $office = app(TenantContext::class)->locationId;
        abort_unless($office, 422);
        $inventory = app(RequestInventory::class);
        $queries = [];
        foreach (['asset_model' => AssetModel::class, 'accessory' => Accessory::class, 'consumable' => Consumable::class] as $type => $class) {
            $table = (new $class)->getTable();
            $query = $class::query()->select(["{$table}.id", "{$table}.name", "{$table}.category_id"])->selectRaw('? as type', [$type]);
            if ($type === 'asset_model') {
                $stock = Asset::query()->selectRaw('COUNT(*)')->whereColumn('model_id', "{$table}.id")
                    ->where('location_id', $office)->whereNull('assigned_to')->where('requestable', 1)
                    ->whereHas('status', fn ($q) => $q->where('deployable', 1)->where('archived', 0));
                $inventory->scopeCompany($stock, 'assets.company_id');
                $query->selectSub($stock, 'stock');
            } else {
                $query->where("{$table}.location_id", $office);
                $inventory->scopeCompany($query, "{$table}.company_id");
                $pivot = $type === 'accessory' ? 'accessories_checkout' : 'consumables_users';
                $query->selectRaw("{$table}.qty - (SELECT COUNT(*) FROM {$pivot} WHERE {$pivot}.{$type}_id = {$table}.id) as stock");
            }
            $reserved = RequestItem::query()->selectRaw('COALESCE(SUM(reserved_qty), 0)')
                ->where(fn ($q) => $q->where(fn ($q) => $q->whereNull('fulfilled_id')->where('requested_type', $type)->whereColumn('requested_id', "{$table}.id"))
                    ->orWhere(fn ($q) => $q->where('fulfilled_type', $type)->whereColumn('fulfilled_id', "{$table}.id")))
                ->whereHas('request', fn ($q) => $q->where('office_id', $office)->whereIn('approval_status', RequestWorkflow::APPROVED)
                    ->whereIn('fulfillment_status', RequestWorkflow::OPEN_FULFILLMENT));
            $query->selectSub($reserved, 'reserved');
            if (! empty($filters['q'])) {
                $query->where("{$table}.name", 'like', '%'.$filters['q'].'%');
            }
            if (! empty($filters['category_id'])) {
                $query->where("{$table}.category_id", (int) $filters['category_id']);
            }
            if (! empty($filters['type']) && $filters['type'] !== $type) {
                $query->whereRaw('1 = 0');
            }
            $queries[] = $query->toBase();
        }
        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }
        $page = DB::query()->fromSub($union, 'catalog')->whereRaw('stock > reserved')->orderBy('name')->orderBy('type')->orderBy('id')
            ->paginate(min(100, max(1, $perPage)))->withQueryString();
        $models = [];
        foreach (['asset_model' => AssetModel::class, 'accessory' => Accessory::class, 'consumable' => Consumable::class] as $type => $class) {
            $models[$type] = $class::with('category')->whereIn('id', $page->getCollection()->where('type', $type)->pluck('id'))->get()->keyBy('id');
        }
        $page->setCollection($page->getCollection()->map(function ($row) use ($models) {
            $model = $models[$row->type][$row->id];

            return (object) ['id' => $row->id, 'type' => $row->type, 'name' => $row->name,
                'category' => $model->category?->name ?? '', 'image_url' => $model->getImageUrl(),
                'available_qty' => (int) ($row->stock - $row->reserved),
                'created_timestamp' => $model->created_at?->timestamp ?? 0, 'details' => []];
        }));

        return $page;
    }
}
