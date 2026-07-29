<?php

namespace GovStore\Tracking\Repositories;

use GovStore\Tracking\Models\TrackingReference;
use GovStore\Tracking\Models\TrackingProjectionCache;
use GovStore\Tracking\Jobs\RebuildTrackingProjectionJob;

class CachedTrackingProjectionRepository implements TrackingProjectionRepositoryInterface
{
    protected EloquentTrackingProjectionRepository $fallbackLiveRepo;

    public function __construct(EloquentTrackingProjectionRepository $fallbackLiveRepo)
    {
        $this->fallbackLiveRepo = $fallbackLiveRepo;
    }

    public function getLifecycleSummary(TrackingReference $reference): array
    {
        $cache = TrackingProjectionCache::where('tracking_reference_id', $reference->id)->first();

        // Graceful fallback for missing caches or during initial setup
        if (!$cache) {
            $metrics = $this->fallbackLiveRepo->getLifecycleSummary($reference);
            
            // Queue an asynchronous background job to build the cached record safely
            RebuildTrackingProjectionJob::dispatch($reference->id);
            
            return $metrics;
        }

        return [
            'planned' => $cache->planned,
            'ordered' => $cache->ordered,
            'received' => $cache->received,
            'deployed' => $cache->deployed,
            'disposed' => $cache->disposed,
        ];
    }
}