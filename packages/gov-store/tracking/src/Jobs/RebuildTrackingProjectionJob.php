<?php

namespace GovStore\Tracking\Jobs;

use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingProjectionCache;
use GovStore\Tracking\Repositories\EloquentTrackingProjectionRepository;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class RebuildTrackingProjectionJob implements ShouldQueue, \GovStore\TenantScope\Contracts\GlobalTenantMaintenance
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $initiativeId;

    public function __construct(int $initiativeId)
    {
        $this->initiativeId = $initiativeId;
    }

    /**
     * Rebuild the materialized projection metrics using the underlying
     * live Eloquent query engine and save/cache the results.
     */
    public function handle(EloquentTrackingProjectionRepository $liveRepo): void
    {
        DB::transaction(function () use ($liveRepo) {
            $initiative = Initiative::withoutGlobalScopes()->whereKey($this->initiativeId)->lockForUpdate()->first();
            if ($initiative) {
                $metrics = $liveRepo->getLifecycleSummary($initiative);
                TrackingProjectionCache::updateOrCreate(
                    ['tracking_reference_id' => $initiative->id],
                    [
                        'planned' => $metrics['planned'],
                        'ordered' => $metrics['ordered'],
                        'received' => $metrics['received'],
                        'deployed' => $metrics['deployed'],
                        'disposed' => $metrics['disposed'],
                        'updated_at' => now(),
                    ]
                );
            }
        });
    }
}
