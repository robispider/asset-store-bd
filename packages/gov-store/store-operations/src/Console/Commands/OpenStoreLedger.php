<?php

namespace GovStore\StoreOperations\Console\Commands;

use App\Models\{Accessory, Asset, AssetModel, Component, Consumable, Location, User};
use GovStore\StoreOperations\Models\{Document, InventoryMovement};
use GovStore\StoreOperations\Services\{DocumentNumberService, LedgerPostingService};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class OpenStoreLedger extends Command
{
    protected $signature = 'govstore:ledger-open {location_id} {--actor= : Existing user ID recorded as the cut-over actor}';
    protected $description = 'Record Snipe-IT on-hand quantities as immutable opening movements for one office';

    public function handle(DocumentNumberService $numbers, LedgerPostingService $ledger): int
    {
        $locationId = (int) $this->argument('location_id');
        $actorId = filter_var($this->option('actor'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $location = Location::query()->find($locationId);
        if (! $location || ! $actorId || ! User::query()->whereKey($actorId)->whereNull('deleted_at')->exists()) {
            $this->error('A valid office and --actor user ID are required. No rows were changed.');

            return self::FAILURE;
        }

        try {
            $result = DB::transaction(function () use ($location, $locationId, $actorId, $numbers, $ledger) {
                if (DB::table('gov_store_ledger_openings')->where('location_id', $locationId)->lockForUpdate()->exists()) {
                    throw new \RuntimeException('This office already has an opening record.');
                }

                $now = now();
                $document = Document::withoutGlobalScopes()->create([
                    'document_number' => $numbers->generate('OB', 'gov_documents', 'document_number'),
                    'type' => 'opening', 'status' => 'POSTED', 'compiled_profile_snapshot' => ['kind' => 'opening_balance'],
                    'company_id' => $location->company_id, 'location_id' => $locationId, 'created_by' => $actorId,
                    'drafted_by' => $actorId, 'posted_by' => $actorId, 'posted_at' => $now, 'managed_by' => $actorId,
                ]);

                $count = 0;
                foreach ([Consumable::class => 'consumable', Accessory::class => 'accessory', Component::class => 'component', AssetModel::class => 'assetmodel'] as $class => $morphType) {
                    $query = $class::withoutGlobalScopes()->whereNull('deleted_at')->orderBy('id');
                    if ($class !== AssetModel::class) {
                        $query->where('location_id', $locationId);
                    }
                    $query
                        ->chunkById(200, function ($items) use ($class, $morphType, $document, $location, $locationId, $actorId, &$count, $ledger) {
                            foreach ($items as $item) {
                                $item = $class::withoutGlobalScopes()->whereKey($item->id)->lockForUpdate()->firstOrFail();
                                if ($item->company_id && (int) $item->company_id !== (int) $location->company_id) {
                                    throw new \RuntimeException('A stock item has a conflicting company. Cut-over stopped.');
                                }
                                $onHand = $class === AssetModel::class
                                    ? Asset::where('model_id', $item->id)->where('location_id', $locationId)->whereNull('assigned_to')
                                        ->whereHas('status', fn ($query) => $query->where('deployable', 1)->where('archived', 0))->count()
                                    : max(0, (int) $item->numRemaining());
                                $existingBalance = InventoryMovement::withoutGlobalScopes()
                                    ->where('stockable_type', $morphType)->where('stockable_id', $item->id)
                                    ->where('location_id', $locationId)
                                    ->selectRaw("COALESCE(SUM(CASE WHEN movement_type = 'IN' THEN quantity ELSE -quantity END), 0) AS balance")
                                    ->value('balance');
                                $difference = $onHand - (int) $existingBalance;
                                if ($difference === 0) {
                                    continue;
                                }
                                $quantity = abs($difference);
                                $document->items()->create([
                                    'product_type' => $morphType, 'product_id' => $item->id,
                                    'quantity' => $quantity, 'unit_cost' => null,
                                ]);
                                $ledger->postMovement($morphType, (int) $item->id, $difference > 0 ? 'IN' : 'OUT', $quantity, $document,
                                    $location->company_id ? (int) $location->company_id : null, $locationId, $actorId,
                                    'Opening reconciliation at '.now()->toDateString().'; Snipe-IT on-hand minus prior ledger balance.');
                                $count++;
                            }
                        });
                }

                DB::table('gov_store_ledger_openings')->insert([
                    'location_id' => $locationId, 'document_id' => $document->id,
                    'opened_at' => $now, 'opened_by' => $actorId, 'created_at' => $now, 'updated_at' => $now,
                ]);

                return [$document->document_number, $count];
            }, 3);
        } catch (\Throwable $exception) {
            report($exception);
            $this->error('Ledger cut-over failed; its database transaction was rolled back. Check the restricted application log.');

            return self::FAILURE;
        }

        [$number, $count] = $result;
        $this->info("Office {$locationId} opened with document {$number}; {$count} stock lines recorded.");

        return self::SUCCESS;
    }
}
