<?php

namespace GovStore\CustomRequests\Console\Commands;

use App\Models\Accessory;
use App\Models\Consumable;
use GovStore\CustomRequests\Models\Request;
use GovStore\StoreOperations\Models\InventoryMovement;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Read-only diagnostic. Historical repairs require an independently reviewed mapping and a backup. */
class RequestReconciliation extends Command
{
    protected $signature = 'gov-requests:reconcile {--office= : Required office ID}';

    protected $description = 'Report unresolved routing, historic checkout duplication and stock differences without changing data';

    public function handle(): int
    {
        $office = filter_var($this->option('office'), FILTER_VALIDATE_INT);
        if (! $office || ! DB::table('locations')->where('id', $office)->whereNull('deleted_at')->exists()) {
            $this->error('Supply an existing office ID.');

            return self::FAILURE;
        }
        // CLI has no selected working context. Each unscoped inventory query has an explicit office boundary.
        $report = ['office_id' => $office,
            'unresolved_office_count' => Request::withTrashed()->whereNull('office_id')->count(),
            'completed_but_partial' => Request::where('office_id', $office)->where('fulfillment_status', 'partially_issued')
                ->whereHas('items', fn ($q) => $q->where('line_approval_status', 'approved'))
                ->whereDoesntHave('items', fn ($q) => $q->where('line_approval_status', 'approved')->whereColumn('issued_qty', '<', 'approved_qty'))
                ->pluck('id')->all(),
            'self_approved' => Request::where('office_id', $office)->where('resolved_policy', '!=', 'AUTO_APPROVE')
                ->where(fn ($q) => $q->whereColumn('approved_by', 'requested_by')->orWhereColumn('primary_decided_by', 'requested_by'))->pluck('id')->all(),
            'legacy_consumable_checkout_rows' => DB::table('consumables_users as checkout')->join('consumables as stock', 'stock.id', '=', 'checkout.consumable_id')
                ->where('stock.location_id', $office)->where('checkout.note', 'like', 'Logged in Goods Issue:%')->count(),
            'stock' => [],
        ];
        foreach ([Accessory::class, Consumable::class] as $class) {
            foreach ($class::withoutGlobalScopes()->where('location_id', $office)->whereNull('deleted_at')->cursor() as $model) {
                $latest = InventoryMovement::withoutGlobalScopes()->where('location_id', $office)
                    ->where('stockable_type', $class)->where('stockable_id', $model->id)->orderByDesc('created_at')->orderByDesc('id')->value('balance_after');
                $remaining = $model->numRemaining();
                $report['stock'][] = ['type' => strtolower(class_basename($class)), 'id' => $model->id,
                    'ledger_balance' => $latest, 'snipe_remaining' => $remaining,
                    'difference' => $latest === null ? null : $remaining - $latest,
                    'baseline_missing' => $latest === null];
            }
        }
        $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
