<?php

namespace GovStore\Tracking\Listeners;

use GovStore\Tracking\Events\AssetsReceivedViaGRN;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingTimeline;
use App\Models\Asset;
use Illuminate\Support\Facades\DB;

class AssociateAssetsToProgramme
{
    public function handle(AssetsReceivedViaGRN $event): void
    {
        // 1. Resolve the Tracking Code
        $trackingCode = TrackingCode::where('tracking_code', $event->trackingCodeString)->first();

        if (!$trackingCode) {
            return; 
        }

        DB::transaction(function () use ($event, $trackingCode) {
            $associationData = [];
            $now = now();
            
            // 2. Iterate through received assets and dynamically resolve their categories and locations
            foreach ($event->assetIds as $assetId) {
                // Fetch the core Snipe-IT asset to read its real-time configurations
                $asset = Asset::find($assetId);
                
                if ($asset) {
                    $associationData[] = [
                        'tracking_code_id'  => $trackingCode->id,
                        'category_id'       => $asset->model->category_id, // Dynamically resolved
                        'location_id'       => $asset->location_id ?? $trackingCode->initiative->manager_location_id, // Dynamically resolved
                        'quantity'          => 1,
                        'associatable_type' => Asset::class,
                        'associatable_id'   => $assetId,
                        'status'            => 'ACTIVE',
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ];
                }
            }

            if (!empty($associationData)) {
                // Save safely using insertOrIgnore
                TrackingAssociation::insertOrIgnore($associationData);
            }

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