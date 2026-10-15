<?php

namespace GovStore\StoreOperations\Services;

use App\Models\AssetModel;
use Illuminate\Support\Collection;

/**
 * One list of everything a store holds, whatever Snipe-IT calls it. Balances are the office ledger's;
 * the item type is only a filter. Rows are plain arrays for the register page and its CSV export.
 */
class StockRegisterQuery
{
    public const STATUSES = ['all', 'attention', 'low', 'out'];
    public const SORTS = ['attention', 'name', 'balance', 'recent'];

    public function __construct(private StockLedgerReader $ledger) {}

    public function rows(int $locationId): Collection
    {
        $summary = $this->ledger->officeSummary($locationId);
        $documents = $this->ledger->briefDocuments($summary->map(fn ($row) => (object) [
            'document_type' => $row['last_document_type'], 'document_id' => $row['last_document_id'],
        ])->values());

        $rows = collect();
        foreach (StockLedgerReader::TYPES as $type => $class) {
            $ledgerIds = $summary->keys()->filter(fn ($key) => str_starts_with($key, $type.':'))
                ->map(fn ($key) => (int) substr($key, strlen($type) + 1))->values();
            $query = $class::withoutGlobalScopes()->with('category')->whereNull('deleted_at');
            if ($class === AssetModel::class) {
                // Models are shared by every office: list those this office's ledger holds.
                $query->with('manufacturer')->whereIn('id', $ledgerIds);
            } else {
                $query->where(fn ($q) => $q->where('location_id', $locationId)->orWhereIn('id', $ledgerIds));
            }

            foreach ($query->get() as $item) {
                $rows->push($this->row($type, $item, $summary->get($type.':'.$item->id), $documents));
            }
        }

        return $rows;
    }

    private function row(string $type, $item, ?array $ledger, Collection $documents): array
    {
        $reorder = (int) $item->min_amt > 0 ? (int) $item->min_amt : null;
        $balance = $ledger ? (int) $ledger['balance'] : null;
        $status = match (true) {
            $balance === null => 'none',
            $balance <= 0 => 'out',
            $reorder !== null && $balance <= $reorder => 'low',
            default => 'ok',
        };
        $last = $ledger && $ledger['last_document_id'] ? $documents->get($ledger['last_document_id']) : null;

        return [
            'type' => $type,
            'id' => (int) $item->id,
            'name' => (string) $item->name,
            'code' => $item->item_no ?: ($item->model_number ?: null),
            'category' => $item->category->name ?? null,
            'manufacturer' => $type === 'assetmodel' ? ($item->manufacturer->name ?? null) : null,
            'balance' => $balance,
            'reorder' => $reorder,
            'status' => $status,
            'rank' => ['out' => 0, 'low' => 1, 'ok' => 2, 'none' => 3][$status],
            // Bar against three times the reorder level, so "full" means comfortably stocked.
            'level' => $balance === null ? 0 : ($reorder ? min(100, (int) round(max(0, $balance) / ($reorder * 3) * 100)) : ($balance > 0 ? 100 : 0)),
            'last_at' => $ledger['last_at'] ?? null,
            'last_document' => $last['number'] ?? null,
            'last_kind' => $last['kind'] ?? null,
        ];
    }

    /** Applies the register's search, type, status and sort choices. */
    public function filter(Collection $rows, array $filters): Collection
    {
        $q = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $type = $filters['type'] ?? 'all';
        $status = $filters['status'] ?? 'all';

        $rows = $rows->filter(function (array $row) use ($q, $type, $status) {
            if ($type !== 'all' && $row['type'] !== $type) {
                return false;
            }
            if ($status === 'attention' && ! in_array($row['status'], ['out', 'low'], true)) {
                return false;
            }
            if (in_array($status, ['low', 'out'], true) && $row['status'] !== $status) {
                return false;
            }

            return $q === '' || str_contains(mb_strtolower($row['name'].' '.$row['code'].' '.$row['category'].' '.$row['manufacturer']), $q);
        });

        $sorted = match ($filters['sort'] ?? 'attention') {
            'name' => $rows->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE),
            'balance' => $rows->sortBy([
                fn ($a, $b) => ($a['balance'] ?? PHP_INT_MAX) <=> ($b['balance'] ?? PHP_INT_MAX),
                fn ($a, $b) => strnatcasecmp($a['name'], $b['name']),
            ]),
            'recent' => $rows->sortByDesc('last_at'),
            default => $rows->sortBy([['rank', 'asc'], ['name', 'asc']]),
        };

        return $sorted->values();
    }

    /** Only the choices that differ from the defaults, for links and pagination. */
    public static function query(array $filters, array $changes = []): array
    {
        $merged = array_merge($filters, $changes);
        $defaults = ['q' => '', 'type' => 'all', 'status' => 'all', 'sort' => 'attention'];

        return array_filter($merged, fn ($value, $key) => $value !== null && $value !== ($defaults[$key] ?? null), ARRAY_FILTER_USE_BOTH);
    }

    /** Figures for the summary tiles and filter counts, over the whole store. */
    public function counts(Collection $rows): array
    {
        $assets = $rows->where('type', 'assetmodel');

        return [
            'all' => $rows->count(),
            'attention' => $rows->whereIn('status', ['out', 'low'])->count(),
            'low' => $rows->where('status', 'low')->count(),
            'out' => $rows->where('status', 'out')->count(),
            'types' => collect(StockLedgerReader::TYPES)->map(fn ($class, $type) => $rows->where('type', $type)->count())->all(),
            'type_count' => $rows->pluck('type')->unique()->count(),
            'asset_units' => (int) $assets->sum(fn ($row) => max(0, (int) $row['balance'])),
            'asset_models' => $assets->count(),
        ];
    }
}
