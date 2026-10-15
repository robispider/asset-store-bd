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
    /**
     * Filter and paginate before hydrating inventory models. No per-item count queries.
     * Filters: q, type, category_id, free (only items with something free) and item ("type:id", one row).
     */
    public function paginate(array $filters = [], int $perPage = 24)
    {
        // D1: items the office stocks but has promised in full stay listed (unaddable) instead of vanishing.
        $page = $this->rows($filters)
            ->orderByRaw('CASE WHEN stock > reserved THEN 0 ELSE 1 END')->orderBy('name')->orderBy('type')->orderBy('id')
            ->paginate(min(100, max(1, $perPage)))->withQueryString();

        return $this->hydrate($page);
    }

    /** One catalogue row ("type:id") as the catalogue shows it, or null when this office does not stock it. */
    public function find(string $key): ?object
    {
        return $this->paginate(['item' => $key], 1)->first();
    }

    /** How many items each kind has under the other filters, for the kind tabs: ['' => all, type => n]. */
    public function counts(array $filters = []): array
    {
        $counts = $this->rows(array_diff_key($filters, ['type' => 1]))->groupBy('type')->selectRaw('type, COUNT(*) as aggregate')
            ->pluck('aggregate', 'type')->map(fn ($n) => (int) $n);

        return ['' => $counts->sum()] + collect(RequestWorkflow::TYPES)->mapWithKeys(fn ($type) => [$type => $counts->get($type, 0)])->all();
    }

    private function rows(array $filters)
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
            if (! empty($filters['item'])) {
                [$itemType, $itemId] = array_pad(explode(':', (string) $filters['item'], 2), 2, 0);
                $itemType === $type ? $query->where("{$table}.id", (int) $itemId) : $query->whereRaw('1 = 0');
            }
            $queries[] = $query->toBase();
        }
        $union = array_shift($queries);
        foreach ($queries as $query) {
            $union->unionAll($query);
        }
        $rows = DB::query()->fromSub($union, 'catalog')->whereRaw('stock > 0');
        if (! empty($filters['free'])) {
            $rows->whereRaw('stock > reserved');
        }

        return $rows;
    }

    private function hydrate($page)
    {
        $models = [];
        foreach (['asset_model' => AssetModel::class, 'accessory' => Accessory::class, 'consumable' => Consumable::class] as $type => $class) {
            $models[$type] = $class::with('category')->whereIn('id', $page->getCollection()->where('type', $type)->pluck('id'))->get()->keyBy('id');
        }
        $page->setCollection($page->getCollection()->map(function ($row) use ($models) {
            $model = $models[$row->type][$row->id];

            return (object) ['id' => (int) $row->id, 'type' => $row->type, 'name' => $row->name, 'category_id' => $row->category_id ? (int) $row->category_id : null,
                'category' => $model->category?->name ?? '', 'image_url' => $model->getImageUrl(),
                'available_qty' => max(0, (int) ($row->stock - $row->reserved)), 'stock' => (int) $row->stock,
                'created_timestamp' => $model->created_at?->timestamp ?? 0, 'details' => []];
        }));

        return $page;
    }

    /**
     * "Often requested here": the items this office asked for most in the last 90 days, from real
     * request lines, so the shortcuts fit the office instead of a fixed English word list.
     *
     * @return array<int, array{type:string, id:int, name:string}>
     */
    public function often(int $limit = 5, ?int $requester = null): array
    {
        $office = app(TenantContext::class)->locationId;
        if (! $office) {
            return [];
        }
        $rows = RequestItem::query()->join('custom_service_requests as requests', 'requests.id', '=', 'custom_service_request_items.request_id')
            ->where('requests.office_id', $office)->whereNull('requests.deleted_at')
            ->where('requests.created_at', '>=', now()->subDays($requester ? 180 : 90))
            ->when($requester, fn ($q) => $q->where('requests.requested_by', $requester))
            ->whereIn('custom_service_request_items.requested_type', RequestWorkflow::TYPES)
            ->groupBy('custom_service_request_items.requested_type', 'custom_service_request_items.requested_id')
            ->orderByRaw('COUNT(*) DESC')->limit($limit)
            ->get(['custom_service_request_items.requested_type as type', 'custom_service_request_items.requested_id as id']);
        $presenter = app(\GovStore\CustomRequests\Support\RequestPresenter::class);
        $presenter->preloadItems($rows->map(fn ($row) => [$row->type, $row->id]));

        return $rows->map(fn ($row) => ['type' => $row->type, 'id' => (int) $row->id, 'name' => $presenter->itemName($row->type, (int) $row->id)])
            ->reject(fn ($item) => $item['name'] === $presenter->t('unknown_item'))->values()->all();
    }

    /**
     * Quick-add shortcuts: what this person asked for most in this office (last 180 days), or what the
     * office asks for most when they have no history yet.
     *
     * @return array{items: array, personal: bool}
     */
    public function usual(int $requester, int $limit = 5): array
    {
        $mine = $this->often($limit, $requester);

        return $mine ? ['items' => $mine, 'personal' => true] : ['items' => $this->often($limit), 'personal' => false];
    }
}
