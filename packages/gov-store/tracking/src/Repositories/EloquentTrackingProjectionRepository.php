<?php

namespace GovStore\Tracking\Repositories;

use App\Models\Asset;
use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingTarget;
use GovStore\Tracking\Models\TrackingAssociation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class EloquentTrackingProjectionRepository implements TrackingProjectionRepositoryInterface
{
    public function getLifecycleSummary(TrackingReference $reference): array
    {
        $summary = [
            'planned' => 0,
            'ordered' => 0,
            'received' => 0,
            'deployed' => 0,
            'disposed' => 0,
        ];

        // 1. PLANNED (Set in our targeting module)
        $summary['planned'] = (int) TrackingTarget::where('tracking_reference_id', $reference->id)
            ->sum('planned_qty');

        // Extract associated core asset IDs for direct state evaluation
        $associatedAssetIds = TrackingAssociation::where('tracking_reference_id', $reference->id)
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->pluck('associatable_id')
            ->toArray();

        if (!empty($associatedAssetIds)) {
            // 2. RECEIVED (Asset exists in inventory and is ready to deploy)
            $summary['received'] = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereHas('assetstatus', function ($query) {
                    $query->where('deployable', 1)->where('pending', 0)->where('archived', 0);
                })
                ->count();

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

        // 5. ORDERED / APPROVED (Check integrations against storefront custom-requests module)
        if (Schema::hasTable('custom_service_requests') && Schema::hasTable('custom_service_request_items')) {
            $associatedRequestIds = TrackingAssociation::where('tracking_reference_id', $reference->id)
                ->where('associatable_type', 'GovStore\CustomRequests\Models\ServiceRequest')
                ->where('status', 'ACTIVE')
                ->pluck('associatable_id')
                ->toArray();

            if (!empty($associatedRequestIds)) {
                $summary['ordered'] = (int) DB::table('custom_service_request_items')
                    ->whereIn('service_request_id', $associatedRequestIds)
                    ->whereNotIn('line_approval_status', ['rejected', 'cancelled'])
                    ->sum('approved_qty');
            }
        }

        return $summary;
    }
}
