<?php

namespace GovStore\Tracking\Repositories;

use App\Models\Asset;
use App\Models\AssetModel;
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

        $planned = (int) TrackingTarget::whereIn('tracking_code_id', $trackingCodeIds)
            ->sum('planned_qty');

        $summary['planned'] = $planned;

        $associatedAssetIds = TrackingAssociation::whereIn('tracking_code_id', $trackingCodeIds)
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->pluck('associatable_id')
            ->toArray();

        $received = 0;
        if (!empty($associatedAssetIds)) {
            $received = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereHas('assetstatus', function ($query) {
                    $query->where('deployable', 1)->where('pending', 0)->where('archived', 0);
                })
                ->count();

            $summary['received'] = $received;

            $summary['deployed'] = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereNotNull('assigned_to')
                ->count();

            $summary['disposed'] = (int) Asset::whereIn('id', $associatedAssetIds)
                ->whereHas('assetstatus', function ($query) {
                    $query->where('archived', 1);
                })
                ->count();
        }

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

        // --- DYNAMIC TABLE NAME RESOLUTION ---
        $assetsTable = (new Asset)->getTable();
        $modelsTable = (new AssetModel)->getTable();

        $received = DB::table('gov_tracking_associations')
            ->join($assetsTable, function ($join) use ($assetsTable) {
                $join->on('gov_tracking_associations.associatable_id', '=', $assetsTable . '.id')
                     ->where('gov_tracking_associations.associatable_type', '=', Asset::class);
            })
            ->join($modelsTable, $assetsTable . '.model_id', '=', $modelsTable . '.id')
            ->where('gov_tracking_associations.tracking_code_id', $trackingCodeId)
            ->where('gov_tracking_associations.status', 'ACTIVE')
            ->where($modelsTable . '.category_id', $categoryId)
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