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
}