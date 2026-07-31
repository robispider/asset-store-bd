<?php

namespace GovStore\Tracking\Repositories;

use App\Models\Asset;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingTarget;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingFactDelivery;
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
            'percentage' => 0,
        ];

        // Retrieve all Tracking Codes belonging to this Initiative
        $trackingCodeIds = TrackingCode::where('initiative_id', $initiative->id)->pluck('id')->toArray();

        if (empty($trackingCodeIds)) {
            return $summary;
        }

        // 1. PLANNED (Sum of targets across all tasks under the Initiative)
        $planned = (int) TrackingTarget::whereIn('tracking_code_id', $trackingCodeIds)->sum('planned_qty');
        $summary['planned'] = $planned;

        // 2. RECEIVED (Sum of received quantities directly from the pre-compiled Fact Table!)
        // No SQL joins to core assets or hardware tables required.
        $received = (int) TrackingFactDelivery::whereIn('tracking_code_id', $trackingCodeIds)->sum('received_qty');
        $summary['received'] = $received;

        // 3. Overall Progress Percentage
        $percentage = $planned > 0 ? round(($received / $planned) * 100) : 0;
        $summary['percentage'] = $percentage > 100 ? 100 : $percentage;

        // 4. DEPLOYED & DISPOSED (Status tracking evaluated securely using polymorphic links)
        $associatedAssetIds = TrackingAssociation::whereIn('tracking_code_id', $trackingCodeIds)
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->pluck('associatable_id')
            ->toArray();

        if (!empty($associatedAssetIds)) {
            // Retrieve core assets securely using our dynamic table resolver
            $summary['deployed'] = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereNotNull('assigned_to')
                ->count();

            $summary['disposed'] = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereHas('assetstatus', function ($query) {
                    $query->where('archived', 1);
                })
                ->count();
        }

        return $summary;
    }

    public function getTargetProgress(int $trackingCodeId, int $categoryId): array
    {
        $target = DB::table('gov_tracking_targets')
            ->where('tracking_code_id', $trackingCodeId)
            ->where('category_id', $categoryId)
            ->first();

        if (!$target) {
            return ['planned' => 0, 'received' => 0, 'percentage' => 0, 'is_exceeded' => false];
        }

        $planned = $target->planned_qty;

        // Query our pre-compiled Fact Table directly, summing received quantities autonomously!
        $received = (int) TrackingFactDelivery::where('tracking_code_id', $trackingCodeId)
            ->where('category_id', $categoryId)
            ->sum('received_qty');

        $percentage = $planned > 0 ? round(($received / $planned) * 100) : 0;

        return [
            'planned'     => (int) $planned,
            'received'    => (int) $received,
            'percentage'  => $percentage,
            'is_exceeded' => ($received > $planned)
        ];
    }

    public function getMatrixProgress(int $trackingCodeId): array
    {
        // Fetch pre-compiled geographic facts directly from our Delivery Cube
        $facts = TrackingFactDelivery::with([
            'category',
            'location' => function($query) {
                $query->withoutGlobalScopes(); // Defensive bypass to prevent empty lists for non-contextual admins
            }
        ])->where('tracking_code_id', $trackingCodeId)->get();

        $progressMatrix = [];

        foreach ($facts as $fact) {
            $locationId = $fact->location_id;
            $locationName = $fact->location?->name ?? "Office Location #{$locationId}";

            // Find the planned target allocation cell
            $allocation = DB::table('gov_tracking_allocations')
                ->join('gov_tracking_targets', 'gov_tracking_allocations.target_id', '=', 'gov_tracking_targets.id')
                ->where('gov_tracking_targets.tracking_code_id', $trackingCodeId)
                ->where('gov_tracking_targets.category_id', $fact->category_id)
                ->where('gov_tracking_allocations.location_id', $locationId)
                ->first();

            $allocatedQty = $allocation ? $allocation->allocated_qty : 0;
            $received = $fact->received_qty;

            $percentage = $allocatedQty > 0 ? round(($received / $allocatedQty) * 100) : 0;

            $progressMatrix[$locationId]['location_name'] = $locationName;
            $progressMatrix[$locationId]['items'][] = [
                'category_name' => $fact->category->name,
                'allocated'     => $allocatedQty,
                'received'      => $received,
                'percentage'    => $percentage,
                'is_exceeded'   => ($received > $allocatedQty)
            ];
        }

        return $progressMatrix;
    }
}