<?php

namespace GovStore\StoreOperations\Console\Commands;

use App\Models\Accessory;
use App\Models\Component;
use App\Models\Consumable;
use GovStore\StoreOperations\Models\InventoryMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconcileStoreLedger extends Command
{
    protected $signature = 'govstore:ledger-reconcile {--location= : Limit to an opened office}';
    protected $description = 'Read-only comparison of opened office ledgers with native remaining stock';

    public function handle(): int
    {
        $offices = DB::table('gov_store_ledger_openings')->when($this->option('location'),
            fn ($query) => $query->where('location_id', $this->option('location')))->pluck('location_id');
        if ($offices->isEmpty()) {
            $this->warn('No opened office ledgers to reconcile. Opening/history review remains required. No data was changed.');
            return self::SUCCESS;
        }
        $differences = [];
        foreach ([Consumable::class, Accessory::class, Component::class] as $class) {
            $class::withoutGlobalScopes()->whereIn('location_id', $offices)->withTrashed()->orderBy('id')
                ->chunkById(200, function ($items) use (&$differences) {
                    foreach ($items as $item) {
                        $chain = InventoryMovement::withoutGlobalScopes()->where('location_id', $item->location_id)
                            ->where('stockable_type', $item->getMorphClass())->where('stockable_id', $item->id);
                        $balance = (int) (clone $chain)->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")->value('balance');
                        $latest = (int) (clone $chain)->orderByDesc('created_at')->orderByDesc('id')->value('balance_after');
                        $remaining = $item->trashed() ? 0 : (int) $item->numRemaining();
                        if ($balance !== $remaining || $latest !== $balance) {
                            $differences[] = [$item->location_id, $item->getMorphClass(), $item->id, $balance, $latest, $remaining];
                        }
                    }
                });
        }
        $this->table(['Office', 'Type', 'Item', 'Ledger sum', 'Last balance', 'Native remaining'], $differences);
        if ($differences) {
            Log::warning('GovStore ledger reconciliation differences', ['differences' => $differences]);
            $this->error('Differences require reviewed opening/history repair or an adjustment. No data was changed.');
            return self::FAILURE;
        }
        $this->info('Opened office stock agrees with the ledger. No data was changed.');
        return self::SUCCESS;
    }
}
