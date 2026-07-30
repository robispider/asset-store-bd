<?php

namespace GovStore\Tracking\Repositories;

use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingProjectionCache;
use GovStore\Tracking\Jobs\RebuildTrackingProjectionJob;

class CachedTrackingProjectionRepository implements TrackingProjectionRepositoryInterface
{
    protected EloquentTrackingProjectionRepository $fallbackLiveRepo;

    public function __construct(EloquentTrackingProjectionRepository $fallbackLiveRepo)
    {
        $this->fallbackLiveRepo = $fallbackLiveRepo;
    }

    public function getLifecycleSummary(Initiative $initiative): array
    {
        $cache = TrackingProjectionCache::where('tracking_reference_id', $initiative->id)->first();

        // Graceful fallback for missing caches or during initial setup
        if (!$cache) {
            $metrics = $this->fallbackLiveRepo->getLifecycleSummary($initiative);

            // Queue an asynchronous background job to build the cached record safely
            RebuildTrackingProjectionJob::dispatch($initiative->id);

            return $metrics;
        }

        // Calculate dynamic percentage based on cache values
        $percentage = $cache->planned > 0 ? round(($cache->received / $cache->planned) * 100) : 0;
        $percentage = $percentage > 100 ? 100 : $percentage;

        return [
            'planned'    => (int) $cache->planned,
            'ordered'    => (int) $cache->ordered,
            'received'   => (int) $cache->received,
            'deployed'   => (int) $cache->deployed,
            'disposed'   => (int) $cache->disposed,
            'percentage' => $percentage, // Added key
        ];
    }
}