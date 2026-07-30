<?php

namespace GovStore\Tracking\Repositories;

use App\Models\Asset;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingTarget;
use GovStore\Tracking\Models\TrackingAssociation;
use Illuminate\Support\Facades\DB;

class EloquentTrackingProjectionRepository implements TrackingProjectionRepositoryInterface
{
    public function getLifecycleSummary(Initiative $initiative): array
    {
        $summary = [
            'planned'    => 0,
            'ordered'    => 0,
            'received'   => 0,
            'deployed'   => 0,
            'disposed'   => 0,
            'percentage' => 0, // Added key
        ];

        // Retrieve all Tracking Codes belonging to this Initiative
        $trackingCodeIds = TrackingCode::where('initiative_id', $initiative->id)->pluck('id')->toArray();

        if (empty($trackingCodeIds)) {
            return $summary;
        }

        // 1. PLANNED (Sum of targets under all tracking codes)
        $planned = (int) TrackingTarget::whereIn('tracking_code_id', $trackingCodeIds)
            ->sum('planned_qty');

        $summary['planned'] = $planned;

        // Extract associated core asset IDs
        $associatedAssetIds = TrackingAssociation::whereIn('tracking_code_id', $trackingCodeIds)
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->pluck('associatable_id')
            ->toArray();

        $received = 0;
        if (!empty($associatedAssetIds)) {
            // 2. RECEIVED (Asset exists in inventory and is ready to deploy)
            $received = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereHas('assetstatus', function ($query) {
                    $query->where('deployable', 1)->where('pending', 0)->where('archived', 0);
                })
                ->count();

            $summary['received'] = $received;

            // 3. DEPLOYED / OPERATIONAL (Asset is assigned directly to holder)
            $summary['deployed'] = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereNotNull('assigned_to')
                ->count();

            // 4. DISPOSED (Asset has been decommissioned or archived in core)
            $summary['disposed'] = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereHas('assetstatus', function ($query) {
                    $query->where('archived', 1);
                })
                ->count();
        }

        // Calculate top-level operational percentage health
        $percentage = $planned > 0 ? round(($received / $planned) * 100) : 0;
        $summary['percentage'] = $percentage > 100 ? 100 : $percentage; // Cap at 100% for macro indicator

        return $summary;
    }
}