<?php

namespace GovStore\Tracking\Repositories;

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
            'percentage' => 0,
        ];

        $trackingCodeIds = TrackingCode::where('initiative_id', $initiative->id)->pluck('id')->toArray();
        if (empty($trackingCodeIds)) {
            return $summary;
        }

        // 1. PLANNED (Sum of targets across all tasks under the Initiative)
        $planned = (int) TrackingTarget::whereIn('tracking_code_id', $trackingCodeIds)->sum('planned_qty');
        $summary['planned'] = $planned;

        // 2. RECEIVED (Sum of quantity directly from tracking association table)
        // No external SQL joins on hardware or assets required! This is extremely fast.
        $received = (int) TrackingAssociation::whereIn('tracking_code_id', $trackingCodeIds)
            ->where('status', 'ACTIVE')
            ->sum('quantity');

        $summary['received'] = $received;

        // Calculate top-level percentage
        $percentage = $planned > 0 ? round(($received / $planned) * 100) : 0;
        $summary['percentage'] = $percentage > 100 ? 100 : $percentage;

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

        // Query our own associations table, summing quantity directly!
        $received = (int) TrackingAssociation::where('tracking_code_id', $trackingCodeId)
            ->where('category_id', $categoryId)
            ->where('status', 'ACTIVE')
            ->sum('quantity');

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
        $trackingCode = TrackingCode::with([
            'targets.category',
            'targets.allocations.location' => function($query) {
                $query->withoutGlobalScopes(); // Defensive bypass to prevent empty lists for non-contextual admins
            }
        ])->find($trackingCodeId);

        if (!$trackingCode) return [];

        $progressMatrix = [];

        foreach ($trackingCode->targets as $target) {
            foreach ($target->allocations as $alloc) {
                $locationId = $alloc->location_id;
                $locationName = $alloc->location?->name ?? "Office Location #{$locationId}";

                // Sum quantities received specifically at this location under this specific category
                $received = (int) DB::table('gov_tracking_associations')
                    ->where('tracking_code_id', $trackingCodeId)
                    ->where('category_id', $target->category_id)
                    ->where('location_id', $locationId)
                    ->where('status', 'ACTIVE')
                    ->sum('quantity');

                $percentage = $alloc->allocated_qty > 0 ? round(($received / $alloc->allocated_qty) * 100) : 0;

                // Group by location for structured UI rendering
                $progressMatrix[$locationId]['location_name'] = $locationName;
                $progressMatrix[$locationId]['items'][] = [
                    'category_name' => $target->category->name,
                    'allocated'     => $alloc->allocated_qty,
                    'received'      => $received,
                    'percentage'    => $percentage,
                    'is_exceeded'   => ($received > $alloc->allocated_qty)
                ];
            }
        }

        return $progressMatrix;
    }
}