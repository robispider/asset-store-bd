<?php

namespace GovStore\Experimentation\Services;

use App\Models\{Accessory, Asset, AssetModel, Component, Consumable, Location, User};
use GovStore\Experimentation\Models\ExperimentRun;
use GovStore\StoreOperations\Models\{Document, InventoryMovement};
use GovStore\StoreOperations\Services\{DocumentNumberService, LedgerPostingService};
use GovStore\TenantScope\Services\TenantExecution;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Opens only manifest-owned fictional offices; ordinary ledger protections still apply. */
class ExperimentLedgerOpening
{
    public function __construct(private RecordRegistry $records, private TenantExecution $execution,
        private DocumentNumberService $numbers, private LedgerPostingService $ledger) {}

    public function open(ExperimentRun $run, Location $office, User $keeper): Document
    {
        if ($run->wiped_at) {
            throw new RuntimeException('A wiped experiment cannot open an office ledger.');
        }
        $this->assertOwned($run, 'locations', ['id' => $office->id]);
        $this->assertOwned($run, 'users', ['id' => $keeper->id]);

        return $this->execution->run([
            'actor_id' => $keeper->id, 'scope_type' => 'location', 'scope_id' => $office->id,
            'ability' => 'storeops.documents.post',
        ], fn () => DB::transaction(function () use ($run, $office, $keeper) {
            $office = Location::withoutGlobalScopes()->whereNull('deleted_at')->whereKey($office->id)->lockForUpdate()->firstOrFail();
            $opening = DB::table('gov_store_ledger_openings')->where('location_id', $office->id)->lockForUpdate()->first();
            if ($opening) {
                $this->assertOwned($run, 'gov_documents', ['id' => $opening->document_id]);
                $document = Document::withoutGlobalScopes()->whereKey($opening->document_id)->lockForUpdate()->firstOrFail();
                if ($document->type !== 'opening' || $document->status !== 'POSTED'
                    || (int) $document->location_id !== (int) $office->id
                    || (int) $document->company_id !== (int) $office->company_id
                    || (int) $document->posted_by !== (int) $opening->opened_by) {
                    throw new RuntimeException('The experiment office has an inconsistent opening record.');
                }
                $this->records->record($run, 'gov_store_ledger_openings', $opening);

                return $document;
            }

            // Resume may encounter the old component-only receipt. Preserve its immutable history.
            foreach (InventoryMovement::withoutGlobalScopes()->where('location_id', $office->id)->lockForUpdate()->get() as $movement) {
                $this->assertOwned($run, 'gov_inventory_movements', ['id' => $movement->id]);
                if (! in_array($movement->stockable_type, ['consumable', 'accessory', 'component', 'assetmodel'], true)
                    || (int) $movement->company_id !== (int) $office->company_id) {
                    throw new RuntimeException('Review inconsistent experiment movement history before opening this office.');
                }
            }

            $now = now();
            $document = Document::withoutGlobalScopes()->create([
                'document_number' => $this->numbers->generate('OB', 'gov_documents', 'document_number'),
                'type' => 'opening', 'status' => 'POSTED',
                'compiled_profile_snapshot' => ['kind' => 'opening_balance', 'experiment_run_id' => $run->id],
                'company_id' => $office->company_id, 'location_id' => $office->id,
                'created_by' => $keeper->id, 'drafted_by' => $keeper->id, 'managed_by' => $keeper->id,
                'posted_by' => $keeper->id, 'posted_at' => $now,
            ]);
            $this->records->record($run, 'gov_documents', $document->getAttributes());

            foreach ([Consumable::class => 'consumable', Accessory::class => 'accessory', Component::class => 'component', AssetModel::class => 'assetmodel'] as $class => $type) {
                $query = $class::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('id');
                if ($class === AssetModel::class) {
                    $query->whereIn('id', $this->records->ids($run, 'models'));
                } else {
                    $query->where('location_id', $office->id);
                }
                foreach ($query->lockForUpdate()->get() as $item) {
                    $this->assertOwned($run, $item->getTable(), ['id' => $item->id]);
                    if (($class !== AssetModel::class || $item->company_id)
                        && (int) $item->company_id !== (int) $office->company_id) {
                        throw new RuntimeException('An experiment stock item belongs to another company.');
                    }
                    if ($class === AssetModel::class) {
                        $assets = Asset::withoutGlobalScopes()->where('model_id', $item->id)->where('location_id', $office->id)
                            ->whereNull('deleted_at')->lockForUpdate()->get();
                        foreach ($assets as $asset) {
                            $this->assertOwned($run, 'assets', ['id' => $asset->id]);
                            if ((int) $asset->company_id !== (int) $office->company_id) {
                                throw new RuntimeException('An experiment asset belongs to another company.');
                            }
                        }
                        $onHand = $assets->filter(fn ($asset) => ! $asset->assigned_to
                            && $asset->status?->deployable && ! $asset->status->archived)->count();
                    } else {
                        $onHand = (int) $item->numRemaining();
                    }
                    if ($onHand < 0) {
                        throw new RuntimeException('Experiment native remaining stock is negative.');
                    }
                    $balance = InventoryMovement::withoutGlobalScopes()->where('stockable_type', $type)
                        ->where('stockable_id', $item->id)->where('location_id', $office->id)
                        ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")
                        ->value('balance');
                    $latestBalance = InventoryMovement::withoutGlobalScopes()->where('stockable_type', $type)
                        ->where('stockable_id', $item->id)->where('location_id', $office->id)
                        ->orderByDesc('created_at')->orderByDesc('id')->value('balance_after') ?? 0;
                    if ((int) $balance !== (int) $latestBalance) {
                        throw new RuntimeException('Review inconsistent experiment ledger balances before opening this office.');
                    }
                    $difference = $onHand - (int) $balance;
                    if ($difference === 0) {
                        continue;
                    }
                    $document->items()->create(['product_type' => $type, 'product_id' => $item->id,
                        'quantity' => abs($difference), 'unit_cost' => null]);
                    $movement = $this->ledger->postMovement($type, (int) $item->id, $difference > 0 ? 'IN' : 'OUT',
                        abs($difference), $document, (int) $office->company_id, (int) $office->id, (int) $keeper->id,
                        'Fictional experiment opening: native on-hand minus existing ledger balance.');
                    $this->records->record($run, 'gov_inventory_movements', $movement->getAttributes());
                }
            }
            $document->timelines()->create(['state' => 'POSTED', 'user_id' => $keeper->id,
                'notes' => 'Automatic opening balance for fictional experiment office.']);
            $row = ['location_id' => $office->id, 'document_id' => $document->id,
                'opened_at' => $now, 'opened_by' => $keeper->id, 'created_at' => $now, 'updated_at' => $now];
            DB::table('gov_store_ledger_openings')->insert($row);
            $this->records->record($run, 'gov_store_ledger_openings', $row);

            return $document;
        }));
    }

    private function assertOwned(ExperimentRun $run, string $table, array $identity): void
    {
        if (! DB::table('gov_experiment_records')->where('run_id', $run->id)->where('table_name', $table)
            ->where('record_key', $this->records->key($table, $identity))->exists()) {
            throw new RuntimeException('Ledger opening requires records owned by this experiment dataset.');
        }
    }
}
