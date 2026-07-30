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

class RebuildTrackingProjectionJob implements ShouldQueue
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
        $initiative = Initiative::find($this->initiativeId);
        
        if ($initiative) {
            $metrics = $liveRepo->getLifecycleSummary($initiative);

            TrackingProjectionCache::updateOrCreate(
                ['tracking_reference_id' => $initiative->id],
                [
                    'planned'  => $metrics['planned'],
                    'ordered'  => $metrics['ordered'],
                    'received' => $metrics['received'],
                    'deployed' => $metrics['deployed'],
                    'disposed' => $metrics['disposed'],
                    'updated_at' => now(),
                ]
            );
        }
    }
}