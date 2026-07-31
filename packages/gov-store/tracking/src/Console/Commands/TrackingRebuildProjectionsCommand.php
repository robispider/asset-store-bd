<?php

namespace GovStore\Tracking\Console\Commands;

use App\Models\Asset;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingFactDelivery;
use GovStore\Organization\Models\LocationProfile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TrackingRebuildProjectionsCommand extends Command
{
    protected $signature = 'govstore:rebuild-projections {initiative_id? : Optional ID of the specific Initiative to rebuild}';
    protected $description = 'Surgically purges and completely rebuilds the multi-dimensional Fact Table projections from ledger histories';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info("=== Starting GovStore Tracking Rebuild Pipeline ===");

        $initiativeId = $this->argument('initiative_id');

        if ($initiativeId) {
            $initiative = Initiative::find($initiativeId);
            if (!$initiative) {
                $this->error("Initiative ID {$initiativeId} not found.");
                return 1;
            }
            $this->rebuildInitiative($initiative);
        } else {
            // Rebuild all initiatives
            $initiatives = Initiative::all();
            if ($initiatives->isEmpty()) {
                $this->warn("No active initiatives found to rebuild.");
                return 0;
            }

            foreach ($initiatives as $initiative) {
                $this->rebuildInitiative($initiative);
            }
        }

        $this->info("\n=== Rebuild Pipeline Completed Successfully ===");
        return 0;
    }

    /**
     * Surgically purges and rebuilds the fact table for a single Initiative.
     */
    protected function rebuildInitiative(Initiative $initiative): void
    {
        $this->warn("\nRebuilding projections for: '{$initiative->title}' (ID: {$initiative->id})...");

        DB::transaction(function () use ($initiative) {
            // 1. Purge all existing Fact rows for this Initiative
            TrackingFactDelivery::where('initiative_id', $initiative->id)->delete();

            // 2. Fetch all active ledger associations mapped to this Initiative
            $trackingCodeIds = TrackingCode::where('initiative_id', $initiative->id)->pluck('id')->toArray();
            
            if (empty($trackingCodeIds)) {
                $this->line("  ↳ No active tracking codes found. Cleared obsolete facts.");
                return;
            }

            $associations = TrackingAssociation::whereIn('tracking_code_id', $trackingCodeIds)
                ->where('status', 'ACTIVE')
                ->get();

            if ($associations->isEmpty()) {
                $this->line("  ↳ No active associations found. Cleared obsolete facts.");
                return;
            }

            $this->line("  ↳ Compiling " . $associations->count() . " ledger associations into dimensional facts...");

            // 3. Compile the dimensional facts
            // 3. Compile the dimensional facts
            $factData = [];

            foreach ($associations as $assoc) {
                $trackingCode = $assoc->trackingCode;
                
                // FIXED: Default to null fallback values safely
                $modelId = null;
                $manufacturerId = null;
                $supplierId = null;
                $cost = 0.00;

                if ($assoc->associatable_type === Asset::class) {
                    $asset = Asset::find($assoc->associatable_id);
                    if ($asset) {
                        $modelId = $asset->model_id;
                        $manufacturerId = $asset->model ? $asset->model->manufacturer_id : null;
                        $supplierId = $asset->supplier_id;
                        $cost = (double) $asset->purchase_cost;
                    }
                }

                // Compile composite key string. Null values are rendered as empty strings in the implode key.
                $compositeKey = implode('-', [
                    $trackingCode->id,
                    $assoc->location_id,
                    $assoc->category_id,
                    $modelId ?? 'null',
                    $manufacturerId ?? 'null',
                    $supplierId ?? 'null'
                ]);

                if (!isset($factData[$compositeKey])) {
                    $locationProfile = LocationProfile::where('location_id', $assoc->location_id)->first();
                    $geoAreaId = $locationProfile ? $locationProfile->geo_area_id : null;

                    $factData[$compositeKey] = [
                        'initiative_id'     => $initiative->id,
                        'tracking_code_id'  => $trackingCode->id,
                        'funding_type_id'   => $trackingCode->funding_type_id,
                        'fiscal_year'       => $trackingCode->fiscal_year,
                        'location_id'       => $assoc->location_id,
                        'geo_area_id'       => $geoAreaId,
                        'category_id'       => $assoc->category_id,
                        'model_id'          => $modelId,
                        'manufacturer_id'   => $manufacturerId,
                        'supplier_id'       => $supplierId,
                        'received_qty'      => 0,
                        'total_cost'        => 0.00,
                        'transaction_count' => 0,
                        'created_at'        => now(),
                        'updated_at'        => now(),
                    ];
                }

                $factData[$compositeKey]['received_qty']      += $assoc->quantity;
                $factData[$compositeKey]['total_cost']        += $cost;
                $factData[$compositeKey]['transaction_count'] += 1;
            }

            // 4. Bulk insert aggregated dimensions into the Fact Table
            if (!empty($factData)) {
                TrackingFactDelivery::insert(array_values($factData));
            }

            $this->info("  ↳ Successfully compiled " . count($factData) . " unique dimension rows into the Delivery Cube.");
        });
    }
}