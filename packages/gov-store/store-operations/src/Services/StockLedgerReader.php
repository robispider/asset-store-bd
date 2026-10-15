<?php

namespace GovStore\StoreOperations\Services;

use App\Models\Accessory;
use App\Models\AssetModel;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\User;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\InventoryMovement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only access to one office's store ledger: balances, entries and the documents behind them.
 * Every figure shown to store staff comes from here; native Snipe-IT quantities are never read.
 */
class StockLedgerReader
{
    public const TYPES = [
        'consumable' => Consumable::class,
        'accessory' => Accessory::class,
        'component' => Component::class,
        'assetmodel' => AssetModel::class,
    ];

    /** Canonical register key for a stored stockable_type (short morph key or legacy class name). */
    public static function typeKey(string $stockableType): ?string
    {
        return match (strtolower(class_basename($stockableType))) {
            'consumable' => 'consumable',
            'accessory' => 'accessory',
            'component' => 'component',
            'assetmodel', 'asset_model' => 'assetmodel',
            default => null,
        };
    }

    /** Every stockable_type value that may hold this item type's rows. */
    public static function storedKeys(string $type): array
    {
        return $type === 'assetmodel' ? AssetUnitRegister::MORPH_KEYS : [$type, self::TYPES[$type]];
    }

    public function isOpened(int $locationId): bool
    {
        return DB::table('gov_store_ledger_openings')->where('location_id', $locationId)->exists();
    }

    public function openedAt(int $locationId): ?string
    {
        return DB::table('gov_store_ledger_openings')->where('location_id', $locationId)->value('opened_at');
    }

    /**
     * Balance and latest entry for every item on one office's ledger, keyed "type:id".
     *
     * @return Collection<string, array{balance:int, last_at:?string, last_document_id:?string, last_direction:?string}>
     */
    public function officeSummary(int $locationId): Collection
    {
        $totals = InventoryMovement::withoutGlobalScopes()->where('location_id', $locationId)
            ->groupBy('stockable_type', 'stockable_id')
            ->selectRaw("stockable_type, stockable_id, SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE -quantity END) AS balance, MAX(created_at) AS last_at")
            ->get();

        $latest = DB::table('gov_inventory_movements as m')
            ->joinSub(DB::table('gov_inventory_movements')->where('location_id', $locationId)
                ->groupBy('stockable_type', 'stockable_id')
                ->selectRaw('stockable_type, stockable_id, MAX(created_at) AS last_at'), 'l', function ($join) {
                    $join->on('l.stockable_type', '=', 'm.stockable_type')
                        ->on('l.stockable_id', '=', 'm.stockable_id')
                        ->on('l.last_at', '=', 'm.created_at');
                })
            ->where('m.location_id', $locationId)
            ->orderBy('m.id')
            ->get(['m.stockable_type', 'm.stockable_id', 'm.document_type', 'm.document_id', 'm.movement_type', 'm.created_at']);

        $summary = collect();
        foreach ($totals as $row) {
            $type = self::typeKey($row->stockable_type);
            if (! $type) {
                continue;
            }
            $key = $type.':'.$row->stockable_id;
            $current = $summary->get($key, ['balance' => 0, 'last_at' => null, 'last_document_type' => null, 'last_document_id' => null, 'last_direction' => null]);
            $current['balance'] += (int) $row->balance;
            $current['last_at'] = max($current['last_at'], $row->last_at);
            $summary->put($key, $current);
        }
        foreach ($latest as $row) {
            $key = self::typeKey($row->stockable_type).':'.$row->stockable_id;
            if ($summary->has($key) && $summary[$key]['last_at'] === $row->created_at) {
                $summary->put($key, array_merge($summary[$key], [
                    'last_document_type' => $row->document_type, 'last_document_id' => $row->document_id, 'last_direction' => $row->movement_type,
                ]));
            }
        }

        return $summary;
    }

    /** Every entry for one item in one office, oldest first. */
    public function movements(string $type, int $id, int $locationId): Collection
    {
        return InventoryMovement::withoutGlobalScopes()
            ->whereIn('stockable_type', self::storedKeys($type))
            ->where('stockable_id', $id)
            ->where('location_id', $locationId)
            ->orderBy('created_at')->orderBy('id')
            ->get();
    }

    /**
     * Checks the rule the ledger posts by: each balance equals the previous balance plus or minus its quantity.
     * Adds `before` and `after` to each movement as plain attributes.
     */
    public function verifyChain(Collection $movements): array
    {
        // Each stored key (short morph key, or a legacy class name) was posted as its own chain;
        // the item's balance is their sum. Check each chain, show the combined running balance.
        $running = 0;
        $perKey = [];
        $brokenAt = null;
        foreach ($movements as $movement) {
            $signed = $movement->movement_type === 'IN' ? (int) $movement->quantity : -(int) $movement->quantity;
            $movement->setAttribute('before', $running);
            $running += $signed;
            $movement->setAttribute('after', $running);
            $key = $movement->stockable_type;
            $perKey[$key] = ($perKey[$key] ?? 0) + $signed;
            if ($brokenAt === null && ($movement->balance_after === null || (int) $movement->balance_after !== $perKey[$key])) {
                $brokenAt = $movement->id;
            }
        }

        return ['ok' => $brokenAt === null, 'entries' => $movements->count(), 'broken_at' => $brokenAt, 'balance' => $running];
    }

    /** The movement's kind in the store's own words. */
    public static function kind(?object $document): string
    {
        return match ($document->type ?? null) {
            'receipt' => ($document->source_document_id ?? null) ? 'transfer_in' : 'receipt',
            'issue' => 'issue',
            'adjustment' => 'adjustment',
            'transfer' => 'transfer',
            'opening' => 'opening',
            default => 'other',
        };
    }

    /**
     * Number and kind for each referenced document, keyed by id. Rows need document_type and document_id;
     * legacy GoodsIssue / GoodsReceipt / StockAdjustment headers are read from their own tables.
     */
    public function briefDocuments(Collection $rows): Collection
    {
        $brief = collect();
        foreach ($rows->filter(fn ($row) => $row->document_id)->groupBy('document_type') as $class => $group) {
            $ids = $group->pluck('document_id')->unique()->values();
            if ($class === Document::class || ! class_exists($class)) {
                DB::table('gov_documents')->whereIn('id', $ids)->get(['id', 'document_number', 'type', 'source_document_id'])
                    ->each(fn ($d) => $brief->put($d->id, ['number' => $d->document_number, 'type' => $d->type, 'kind' => self::kind($d)]));
                continue;
            }
            $model = new $class;
            if (! Schema::hasTable($model->getTable())) {
                continue;
            }
            $kind = ['goodsissue' => 'issue', 'goodsreceipt' => 'receipt', 'stockadjustment' => 'adjustment'][strtolower(class_basename($class))] ?? 'other';
            $class::withoutGlobalScopes()->whereIn($model->getKeyName(), $ids)->get()->each(fn ($d) => $brief->put($d->getKey(), [
                'number' => $d->document_number ?? $d->issue_no ?? $d->receipt_no ?? $d->adjustment_no ?? null, 'type' => null, 'kind' => $kind,
            ]));
        }

        return $brief;
    }

    /**
     * Who and what stands behind each document: counterparty, reference, people and history.
     *
     * @return Collection<string, array>
     */
    public function documentContext(Collection $movements): Collection
    {
        $isLegacy = fn ($m) => $m->document_type !== Document::class && class_exists((string) $m->document_type);
        $ids = $movements->reject($isLegacy)->pluck('document_id')->filter()->unique()->values();
        $legacy = $this->briefDocuments($movements->filter($isLegacy))
            ->map(fn ($brief, $id) => $brief + ['id' => $id, 'party_key' => null, 'party' => null, 'reference' => null, 'corrects' => null,
                'posted_at' => null, 'drafter' => null, 'poster' => null, 'same_person' => false, 'attachments' => [], 'history' => []]);
        if ($ids->isEmpty()) {
            return $legacy;
        }

        $relations = ['references', 'timelines'];
        if (Schema::hasTable('gov_document_attachments')) {
            $relations[] = 'attachments';
        }
        $documents = Document::withoutGlobalScopes()->with($relations)->whereIn('id', $ids)->get()->keyBy('id');
        $sources = Document::withoutGlobalScopes()->whereIn('id', $documents->pluck('source_document_id')->filter()->unique())
            ->get(['id', 'document_number', 'type', 'location_id'])->keyBy('id');

        $userIds = $documents->flatMap(fn ($d) => [$d->drafted_by, $d->posted_by, $d->created_by, $d->issued_to_user_id])
            ->merge($documents->flatMap(fn ($d) => $d->timelines->pluck('user_id')))->filter()->unique();
        $users = User::withoutGlobalScopes()->whereIn('id', $userIds)->get()->keyBy('id');
        $suppliers = Schema::hasTable('suppliers')
            ? DB::table('suppliers')->whereIn('id', $documents->pluck('supplier_id')->filter()->unique())->pluck('name', 'id') : collect();
        $locationIds = $documents->pluck('destination_location_id')->merge($sources->pluck('location_id'))->filter()->unique();
        $locations = Schema::hasTable('locations') ? DB::table('locations')->whereIn('id', $locationIds)->pluck('name', 'id') : collect();

        $person = function ($id) use ($users) {
            $user = $id ? $users->get($id) : null;

            return $user ? ['name' => trim($user->first_name.' '.$user->last_name), 'role' => $user->jobtitle ?: null] : null;
        };

        return $legacy->union($documents->map(function (Document $document) use ($sources, $suppliers, $locations, $person) {
            $kind = self::kind($document);
            $source = $document->source_document_id ? $sources->get($document->source_document_id) : null;
            [$partyKey, $party] = match ($kind) {
                'receipt' => ['supplier', $suppliers->get($document->supplier_id)],
                'transfer_in' => ['transferred_from', $source ? $locations->get($source->location_id) : null],
                'issue' => ['issued_to', collect([$person($document->issued_to_user_id)['name'] ?? null, $document->issue_department])->filter()->implode(' · ') ?: null],
                'adjustment' => ['reason', $document->adjustment_reason ? __('storeops::storeops.'.strtolower($document->adjustment_reason)) : null],
                'transfer' => ['transferred_to', $locations->get($document->destination_location_id)],
                default => [null, null],
            };
            $reference = $document->references->first();
            $drafter = $person($document->drafted_by ?: $document->created_by);
            $poster = $person($document->posted_by);

            return [
                'id' => $document->id,
                'number' => $document->document_number,
                'type' => $document->type,
                'kind' => $kind,
                'party_key' => $partyKey,
                'party' => $party,
                'reference' => $reference ? trim($reference->reference_type.' '.$reference->reference_number) : null,
                'corrects' => $kind === 'adjustment' && $source ? $source->document_number : null,
                'posted_at' => $document->posted_at,
                'drafter' => $drafter,
                'poster' => $poster,
                'same_person' => $document->posted_by && (int) ($document->drafted_by ?: $document->created_by) === (int) $document->posted_by,
                'attachments' => $document->relationLoaded('attachments') ? $document->attachments->pluck('original_name')->all() : [],
                'history' => $document->timelines->sortBy('created_at')->map(fn ($t) => [
                    'state' => $t->state, 'at' => $t->created_at, 'by' => $person($t->user_id)['name'] ?? null,
                ])->values()->all(),
            ];
        }));
    }
}
