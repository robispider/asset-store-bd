<?php

namespace GovStore\StoreOperations\Console\Commands;

use Illuminate\Console\Command;
use GovStore\StoreOperations\Models\InventoryMovement;
use Illuminate\Support\Facades\DB;

class RepairLedgerBalances extends Command
{
    protected $signature = 'govstore:repair-ledger';
    protected $description = 'Sequentially recalculates and repairs missing running balances (balance_after) in the ledger';

    public function handle()
    {
        $this->info(__('storeops::storeops.scanning_movements'));

        // Balances are chains per stockable item and office. Recompute every
        // chain because a non-null later row can depend on an incorrect earlier one.
        $chains = InventoryMovement::withoutGlobalScopes()
            ->select('stockable_type', 'stockable_id', 'location_id')
            ->groupBy('stockable_type', 'stockable_id', 'location_id')
            ->get();

        if ($chains->isEmpty()) {
            $this->info(__('storeops::storeops.all_balances_healthy'));
            return 0;
        }

        $this->info(__('storeops::storeops.found_unbalanced_items', ['count' => $chains->count()]));

        $negativeChains = [];
        DB::transaction(function () use ($chains, &$negativeChains) {
            foreach ($chains as $chain) {
                $movements = InventoryMovement::withoutGlobalScopes()->where('stockable_type', $chain->stockable_type)
                    ->where('stockable_id', $chain->stockable_id)
                    ->where('location_id', $chain->location_id)
                    ->orderBy('created_at')->orderBy('id')
                    ->get();

                $runningBalance = 0;
                foreach ($movements as $movement) {
                    if ($movement->movement_type === 'IN') {
                        $runningBalance += $movement->quantity;
                    } else {
                        $runningBalance -= $movement->quantity;
                    }
                    if ($runningBalance < 0) {
                        $negativeChains[] = $chain->stockable_type.':'.$chain->stockable_id.'@'.$chain->location_id;
                    }

                    InventoryMovement::withoutGlobalScopes()->whereKey($movement->id)->update(['balance_after' => $runningBalance]);
                }
            }
        });

        $this->info("Recomputed {$chains->count()} item and office balance chains.");
        if ($negativeChains) {
            $this->warn('Negative chains found; resolve missing opening stock before permitting issues.');
            foreach (array_unique($negativeChains) as $chain) {
                $this->line($chain);
            }
        }
        return 0;
    }
}
