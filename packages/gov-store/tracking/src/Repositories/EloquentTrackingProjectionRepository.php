<?php

namespace GovStore\Tracking\Repositories;

use App\Models\Asset;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingTarget;
use GovStore\Tracking\Services\TrackingMonitorMetrics;
use Illuminate\Support\Facades\DB;

class EloquentTrackingProjectionRepository implements TrackingProjectionRepositoryInterface
{
    public function getLifecycleSummary(Initiative $initiative): array
    {
        $summary = [
            'planned' => 0,
            'ordered' => 0,
            'received' => 0,
            'deployed' => 0,
            'disposed' => 0,
            'percentage' => 0,
        ];

        // Retrieve all Tracking Codes belonging to this Initiative
        $codes = TrackingCode::with('targets.allocations')->where('initiative_id', $initiative->id)->where('status', 'ACTIVE')->get();
        $trackingCodeIds = $codes->pluck('id')->all();

        if (empty($trackingCodeIds)) {
            return $summary;
        }

        // 1. PLANNED (Sum of targets across all tasks under the Initiative)
        $planned = (int) TrackingTarget::whereIn('tracking_code_id', $trackingCodeIds)->sum('planned_qty');
        $summary['planned'] = $planned;

        // 2. RECEIVED (Sum of received quantities directly from the pre-compiled Fact Table!)
        // No SQL joins to core assets or hardware tables required.
        $received = (int) TrackingAssociation::whereIn('tracking_code_id', $trackingCodeIds)->where('status', 'ACTIVE')->sum('quantity');
        $summary['received'] = $received;

        // 3. Overall Progress Percentage
        $lines = [];
        $quantities = TrackingAssociation::whereIn('tracking_code_id', $trackingCodeIds)->where('status', 'ACTIVE')->get();
        $metrics = new TrackingMonitorMetrics;
        foreach ($codes as $code) {
            foreach ($code->targets as $target) {
                $rows = $quantities->where('tracking_code_id', $code->id)->where('category_id', $target->category_id);
                $cells = null;
                if ($metrics->plan($code->specificity_level) === '3_MATRIX') {
                    $cells = [];
                    foreach ($target->allocations as $cell) {
                        $cells[$cell->location_id] = [
                            'allocated' => (float) $cell->allocated_qty, 'received' => (float) $rows->where('location_id', $cell->location_id)->sum('quantity')];
                    }
                }
                $lines[] = $metrics->line((float) $target->planned_qty, (float) $rows->sum('quantity'), $cells)['fulfilment'];
            }
        }
        $summary['percentage'] = round(100 * ($metrics->mean($lines) ?? 0), 2);

        // 4. DEPLOYED & DISPOSED (Status tracking evaluated securely using polymorphic links)
        $associatedAssetIds = TrackingAssociation::whereIn('tracking_code_id', $trackingCodeIds)
            ->where('associatable_type', Asset::class)
            ->where('status', 'ACTIVE')
            ->pluck('associatable_id')
            ->toArray();

        $context = app(TenantContext::class);
        if (! empty($associatedAssetIds) && ($context->isGlobal || $context->allowedInventoryLocationIds !== null)) {
            $assets = Asset::withoutGlobalScopes()->whereIn('id', $associatedAssetIds)->whereNull('deleted_at');
            if (! $context->isGlobal) {
                $assets->whereIn('location_id', $context->allowedInventoryLocationIds)
                    ->whereIn('company_id', $context->allowedInventoryCompanyIds ?? []);
            }
            // Retrieve core assets securely using our dynamic table resolver
            $summary['deployed'] = (int) (clone $assets)
                ->whereNotNull('assigned_to')
                ->count();

            $summary['disposed'] = (int) (clone $assets)
                ->whereHas('status', function ($query) {
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

        if (! $target) {
            return ['planned' => 0, 'received' => 0, 'percentage' => 0, 'is_exceeded' => false];
        }

        $planned = $target->planned_qty;

        // Query our pre-compiled Fact Table directly, summing received quantities autonomously!
        $received = (int) TrackingAssociation::where('tracking_code_id', $trackingCodeId)->where('status', 'ACTIVE')
            ->where('category_id', $categoryId)
            ->sum('quantity');

        $percentage = $planned > 0 ? round(($received / $planned) * 100) : 0;

        return [
            'planned' => (int) $planned,
            'received' => (int) $received,
            'percentage' => $percentage,
            'is_exceeded' => ($received > $planned),
        ];
    }

    public function getMatrixProgress(int $trackingCodeId): array
    {
        // Fetch pre-compiled geographic facts directly from our Delivery Cube
        $facts = TrackingAssociation::with([
            'category' => fn ($query) => $query->withoutGlobalScopes(),
            'location' => function ($query) {
                $query->withoutGlobalScopes(); // Defensive bypass to prevent empty lists for non-contextual admins
            },
        ])->where('tracking_code_id', $trackingCodeId)->where('status', 'ACTIVE')
            ->selectRaw('category_id, location_id, SUM(quantity) AS received_qty')->groupBy('category_id', 'location_id')->get();

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
                'allocated' => $allocatedQty,
                'received' => $received,
                'percentage' => $percentage,
                'is_exceeded' => ($received > $allocatedQty),
            ];
        }

        return $progressMatrix;
    }
}
