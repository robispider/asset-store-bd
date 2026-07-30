<?php

namespace GovStore\Tracking\Listeners;

use GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingTimeline;
use Illuminate\Support\Facades\DB;

class AssociateInventoryToProgramme
{
    public function handle(InventoryMaterializedAgainstProgramme $event): void
    {
        $trackingCode = TrackingCode::where('tracking_code', $event->trackingCode)->first();
        if (!$trackingCode) {
            return; 
        }

        DB::transaction(function () use ($event, $trackingCode) {
            $now = now();
            $associationData = [];

            if (!empty($event->associatables)) {
                // Serialized Hardware Path: create separate links for each individual item
                foreach ($event->associatables as $item) {
                    $associationData[] = [
                        'tracking_code_id'  => $trackingCode->id,
                        'category_id'       => $event->categoryId,
                        'quantity'          => 1, // Individual asset qty is 1
                        'associatable_type' => $item['type'],
                        'associatable_id'   => $item['id'],
                        'status'            => 'ACTIVE',
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            } else {
                // Consumables/Bulk Items Path: create a single summarized entry mapping to the ledger event
                $associationData[] = [
                    'tracking_code_id'  => $trackingCode->id,
                    'category_id'       => $event->categoryId,
                    'quantity'          => $event->quantity, // Stores the bulk received amount directly
                    'associatable_type' => 'GovStore\StoreOperations\Models\InventoryMovement', 
                    'associatable_id'   => 0, // Placeholder mapping to general ledger card
                    'status'            => 'ACTIVE',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            TrackingAssociation::insertOrIgnore($associationData);

            // Log activity log timeline
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