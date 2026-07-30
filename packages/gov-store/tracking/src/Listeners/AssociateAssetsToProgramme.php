<?php

namespace GovStore\Tracking\Listeners;

use GovStore\Tracking\Events\AssetsReceivedViaGRN;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingTimeline;
use Illuminate\Support\Facades\DB;

class AssociateAssetsToProgramme
{
    public function handle(AssetsReceivedViaGRN $event): void
    {
        // 1. Resolve the Tracking Code
        $trackingCode = TrackingCode::where('tracking_code', $event->trackingCodeString)->first();

        if (!$trackingCode) {
            return; // Gracefully ignore if the code somehow doesn't exist.
        }

        DB::transaction(function () use ($event, $trackingCode) {
            // 2. Create the Ledger Associations
            $associationData = [];
            $now = now();
            
            foreach ($event->assetIds as $assetId) {
                $associationData[] = [
                    'tracking_code_id'  => $trackingCode->id,
                    'associatable_type' => \App\Models\Asset::class, // Morph to core Snipe-IT Asset
                    'associatable_id'   => $assetId,
                    'status'            => 'ACTIVE',
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ];
            }

            // Bulk insert for high-performance GRN operations (e.g., receiving 5,000 items)
            // Using insertOrIgnore to silently handle potential duplicates
            TrackingAssociation::insertOrIgnore($associationData);

            // 3. Write to the Activity Timeline (Visible on the Workspace)
            $qty = count($event->assetIds);
            
            TrackingTimeline::create([
                'initiative_id' => $trackingCode->initiative_id,
                'event_type'    => 'GRN_RECEIVED',
                'description'   => "Received {$qty} assets via GRN ({$event->grnReferenceNumber}) using Tracking Code '{$trackingCode->tracking_code}'.",
                'actor_id'      => $event->actorId,
                'metadata'      => [
                    'tracking_code' => $trackingCode->tracking_code,
                    'grn_reference' => $event->grnReferenceNumber,
                    'quantity'      => $qty,
                    'override_justification' => $event->overrideReason
                ],
                'occurred_at'   => $now,
            ]);
            
            // 4. Write an Explicit Audit Timeline Event if an Override was provided
            if (!empty($event->overrideReason)) {
                TrackingTimeline::create([
                    'initiative_id' => $trackingCode->initiative_id,
                    'event_type'    => 'OVERSHOOT_OVERRIDE_LOGGED',
                    'description'   => "Target override authorized for GRN ({$event->grnReferenceNumber}). Justification: {$event->overrideReason}",
                    'actor_id'      => $event->actorId,
                    'metadata'      => [
                        'tracking_code' => $trackingCode->tracking_code
                    ],
                    'occurred_at'   => $now,
                ]);
            }
        });
    }
}