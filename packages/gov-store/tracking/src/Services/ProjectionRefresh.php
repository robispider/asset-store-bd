<?php

namespace GovStore\Tracking\Services;

use GovStore\Tracking\Jobs\RebuildTrackingProjectionJob;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingProjectionCache;
use Illuminate\Support\Facades\DB;

class ProjectionRefresh
{
    public static function codes(array $ids): void
    {
        $initiativeIds = TrackingCode::whereIn('id', array_filter($ids))->pluck('initiative_id')->unique()->all();
        foreach ($initiativeIds as $id) {
            self::initiative((int) $id);
        }
    }

    public static function initiative(int $id): void
    {
        // Invalidate within the transaction; rollback restores the previous cache.
        TrackingProjectionCache::where('tracking_reference_id', $id)->delete();
        DB::afterCommit(fn () => RebuildTrackingProjectionJob::dispatch($id));
    }
}
