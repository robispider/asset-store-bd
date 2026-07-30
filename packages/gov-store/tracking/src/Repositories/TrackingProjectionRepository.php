<?php

namespace GovStore\Tracking\Repositories;

use App\Models\Asset;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use Illuminate\Support\Facades\DB;

class TrackingProjectionRepository
{
    /**
     * Calculates the overall health percentage of an entire Initiative.
     * Aggregates all targets across all active tracking codes.
     */
    public function getInitiativeHealth(Initiative $initiative): array
    {
        $trackingCodeIds = TrackingCode::where('initiative_id', $initiative->id)->pluck('id')->toArray();
        
        if (empty($trackingCodeIds)) {
            return ['planned' => 0, 'received' => 0, 'percentage' => 0];
        }

        // Total planned across all targets
        $totalPlanned = DB::table('gov_tracking_targets')
            ->whereIn('tracking_code_id', $trackingCodeIds)
            ->sum('planned_qty');

        if ($totalPlanned == 0) {
            return ['planned' => 0, 'received' => 0, 'percentage' => 0];
        }

        // Total received across all active tracking codes
        $totalReceived = DB::table('gov_tracking_associations')
            ->whereIn('tracking_code_id', $trackingCodeIds)
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->count();

        $percentage = round(($totalReceived / $totalPlanned) * 100);

        // Cap at 100% for the top-level macro health indicator, even if overrides allowed overshoot
        $percentage = $percentage > 100 ? 100 : $percentage;

        return [
            'planned' => (int) $totalPlanned,
            'received' => (int) $totalReceived,
            'percentage' => $percentage
        ];
    }

    /**
     * Calculates the granular progress for each specific category target under a single Tracking Code.
     */
    public function getTargetProgress(int $trackingCodeId, int $categoryId): array
    {
        $target = DB::table('gov_tracking_targets')
            ->where('tracking_code_id', $trackingCodeId)
            ->where('category_id', $categoryId)
            ->first();

        if (!$target) {
            return ['planned' => 0, 'received' => 0, 'percentage' => 0];
        }

        $planned = $target->planned_qty;

        $received = DB::table('gov_tracking_associations')
            ->join('hardware', function ($join) {
                $join->on('gov_tracking_associations.associatable_id', '=', 'hardware.id')
                     ->where('gov_tracking_associations.associatable_type', '=', Asset::class);
            })
            ->join('models', 'hardware.model_id', '=', 'models.id')
            ->where('gov_tracking_associations.tracking_code_id', $trackingCodeId)
            ->where('gov_tracking_associations.status', 'ACTIVE')
            ->where('models.category_id', $categoryId)
            ->count();

        $percentage = $planned > 0 ? round(($received / $planned) * 100) : 0;

        return [
            'planned' => (int) $planned,
            'received' => (int) $received,
            'percentage' => $percentage,
            'is_exceeded' => ($received > $planned)
        ];
    }
}