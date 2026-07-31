<?php

namespace GovStore\Tracking\Listeners;

use GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingFactDelivery;
use GovStore\Tracking\Models\TrackingTimeline;
use GovStore\Organization\Models\LocationProfile;
use Illuminate\Support\Facades\DB;

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
            return; // Exit gracefully if the code does not exist
        }

        DB::transaction(function () use ($event, $trackingCode) {
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
                    'associatable_type' => 'GovStore\StoreOperations\Models\InventoryMovement', 
                    'associatable_id'   => 0,
                    'status'            => 'ACTIVE',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            // High-performance bulk insert bypass
            TrackingAssociation::insertOrIgnore($associationData);

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
                'model_id'         => $event->modelId ?: null,
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
        });
    }
}