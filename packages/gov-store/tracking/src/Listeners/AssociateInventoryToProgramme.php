<?php

namespace GovStore\Tracking\Listeners;

use GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingFactDelivery;
use GovStore\Tracking\Models\TrackingTimeline;
use GovStore\Organization\Models\LocationProfile;
use Illuminate\Support\Facades\DB;
use GovStore\Tracking\Services\ProjectionRefresh;

class AssociateInventoryToProgramme
{
    /**
     * Synchronously intercept the materialization event and compile 
     * the dimensions and additive metrics inside the Fact Table.
     */
    public function handle(InventoryMaterializedAgainstProgramme $event): void
    {
        // 1. Resolve the active Tracking Code task
        $trackingCode = TrackingCode::where('tracking_code', $event->trackingCode)->first();
        if (!$trackingCode) {
            throw new \InvalidArgumentException('Programme receipt code does not exist.');
        }

        DB::transaction(function () use ($event, $trackingCode) {
            // Serialize fact updates, including nullable dimension combinations.
            $trackingCode = TrackingCode::whereKey($trackingCode->id)->lockForUpdate()->firstOrFail();
            $initiative = \GovStore\Tracking\Models\Initiative::withoutGlobalScopes()->whereKey($trackingCode->initiative_id)->lockForUpdate()->firstOrFail();
            $trackingCode->setRelation('initiative', $initiative);
            if ($trackingCode->status !== 'ACTIVE' || $initiative->status !== 'Active'
                || ! app(\GovStore\Tracking\Services\ScopeValidatorService::class)->validateExecutionScope($trackingCode, $event->locationId)['is_valid']) {
                throw new \InvalidArgumentException('Programme receipt is outside the active execution scope.');
            }
            $deliveryKey = hash('sha256', $event->movementId ? 'movement:'.$event->movementId
                : 'legacy:'.json_encode([$event->trackingCode, $event->grnReference,
                    $event->categoryId, $event->locationId, $event->associatables]));
            if (DB::table('gov_tracking_deliveries')->where('delivery_key', $deliveryKey)->exists()) {
                return;
            }
            if ($event->quantity < 1 || $event->categoryId < 1 || $event->locationId < 1) {
                throw new \InvalidArgumentException('Invalid programme delivery dimensions.');
            }
            if ($event->associatables) {
                $ids = array_unique(array_column($event->associatables, 'id'));
                if (count($ids) !== $event->quantity || collect($event->associatables)->contains(fn ($item) => $item['type'] !== \App\Models\Asset::class)
                    || \App\Models\Asset::whereIn('id', $ids)->where('location_id', $event->locationId)
                        ->whereHas('model', fn ($q) => $q->where('category_id', $event->categoryId))->count() !== $event->quantity) {
                    throw new \InvalidArgumentException('Programme receipt assets do not match the payload.');
                }
            }
            $deliveryId = DB::table('gov_tracking_deliveries')->insertGetId([
                'delivery_key' => $deliveryKey, 'tracking_code_id' => $trackingCode->id, 'created_at' => now(),
            ]);
            $now = now();

            // =============================================================
            // A. WRITE POLYMORPHIC LEDGER ASSOCIATIONS (Audit Registry)
            // =============================================================
            $associationData = [];

            if (!empty($event->associatables)) {
                // Serialized Hardware: Map individual asset IDs
                foreach ($event->associatables as $item) {
                    $associationData[] = [
                        'tracking_code_id'  => $trackingCode->id,
                        'category_id'       => $event->categoryId,
                        'location_id'       => $event->locationId,
                        'quantity'          => 1, 
                        'associatable_type' => $item['type'],
                        'associatable_id'   => $item['id'],
                        'status'            => 'ACTIVE',
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            } else {
                // Consumables/Bulk Items: Map a single aggregated movement link
                $associationData[] = [
                    'tracking_code_id'  => $trackingCode->id,
                    'category_id'       => $event->categoryId,
                    'location_id'       => $event->locationId,
                    'quantity'          => $event->quantity, 
                    'associatable_type' => \GovStore\Tracking\Models\TrackingDelivery::class,
                    'associatable_id'   => $deliveryId,
                    'status'            => 'ACTIVE',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            // High-performance bulk insert bypass
            if (TrackingAssociation::insertOrIgnore($associationData) !== count($associationData)) {
                throw new \InvalidArgumentException('Programme assets already belong to another delivery.');
            }

            // =============================================================
            // B. UPDATE/CREATE THE MULTI-DIMENSIONAL FACT ROW (OLAP Cube)
            // =============================================================
            
            // Resolve geographic district/division context from location profile
            $locationProfile = LocationProfile::where('location_id', $event->locationId)->first();
            $geoAreaId = $locationProfile ? $locationProfile->geo_area_id : null;

            // Composite Key Search: Locate matching cell dimensions
            // Composite Key Search: Locate matching cell dimensions (Handling nulls safely)
            $factRow = TrackingFactDelivery::firstOrNew([
                'tracking_code_id' => $trackingCode->id,
                'location_id'      => $event->locationId,
                'category_id'      => $event->categoryId,
                // Bulk stock IDs are not native model IDs.
                'model_id'         => !empty($event->associatables) ? ($event->modelId ?: null) : null,
                'manufacturer_id'  => $event->manufacturerId ?: null,
                'supplier_id'      => $event->supplierId ?: null,
            ]);

            // If a new dimension combination is being registered, populate parent attributes
            if (!$factRow->exists) {
                $factRow->initiative_id   = $trackingCode->initiative_id;
                $factRow->funding_type_id = $trackingCode->funding_type_id;
                $factRow->fiscal_year     = $trackingCode->fiscal_year;
                $factRow->geo_area_id     = $geoAreaId;
            }

            // Increment the additive metric facts
            $factRow->received_qty      += $event->quantity;
            $factRow->total_cost        += $event->totalCost;
            $factRow->transaction_count += 1;
            
            $factRow->save();

            // =============================================================
            // C. WRITE TO AUDIT TIMELINE (Workspace Feed)
            // =============================================================
            TrackingTimeline::create([
                'initiative_id' => $trackingCode->initiative_id,
                'event_type'    => 'GRN_RECEIVED',
                'description'   => "Received {$event->quantity} units via GRN ({$event->grnReference}) using Tracking Code '{$trackingCode->tracking_code}'.",
                'actor_id'      => $event->actorId,
                'metadata'      => [
                    'tracking_code' => $trackingCode->tracking_code,
                    'grn_reference' => $event->grnReference,
                    'quantity'      => $event->quantity,
                    'override_justification' => $event->overrideReason
                ],
                'occurred_at'   => $now,
            ]);

            if (!empty($event->overrideReason)) {
                TrackingTimeline::create([
                    'initiative_id' => $trackingCode->initiative_id,
                    'event_type'    => 'OVERSHOOT_OVERRIDE_LOGGED',
                    'description'   => "Target override authorized for GRN ({$event->grnReference}). Justification: {$event->overrideReason}",
                    'actor_id'      => $event->actorId,
                    'metadata'      => ['tracking_code' => $trackingCode->tracking_code],
                    'occurred_at'   => $now,
                ]);
            }
            ProjectionRefresh::initiative((int) $trackingCode->initiative_id);
        });
    }
}
