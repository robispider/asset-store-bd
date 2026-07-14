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
        $this->info("Scanning inventory movement records for missing balances...");

        // Find unique stockable items that have NULL balances
        $unbalancedItems = InventoryMovement::whereNull('balance_after')
            ->select('stockable_type', 'stockable_id')
            ->groupBy('stockable_type', 'stockable_id')
            ->get();

        if ($unbalancedItems->isEmpty()) {
            $this->info("All ledger balances are already calculated and healthy!");
            return 0;
        }

        $this->info("Found " . $unbalancedItems->count() . " items with uncalculated balances. Repairing sequentially...");

        DB::transaction(function () use ($unbalancedItems) {
            foreach ($unbalancedItems as $item) {
                $movements = InventoryMovement::where('stockable_type', $item->stockable_type)
                    ->where('stockable_id', $item->stockable_id)
                    ->orderBy('created_at', 'asc')
                    ->get();

                $runningBalance = 0;
                foreach ($movements as $movement) {
                    if ($movement->movement_type === 'IN') {
                        $runningBalance += $movement->quantity;
                    } else {
                        $runningBalance -= $movement->quantity;
                    }

                    $movement->update(['balance_after' => $runningBalance]);
                }
            }
        });

        $this->info("Ledger balances repaired successfully!");
        return 0;
    }
}
