<?php

namespace GovStore\Tracking\Repositories;

use GovStore\Tracking\Models\Initiative;

class CachedTrackingProjectionRepository implements TrackingProjectionRepositoryInterface
{
    protected EloquentTrackingProjectionRepository $fallbackLiveRepo;

    public function __construct(EloquentTrackingProjectionRepository $fallbackLiveRepo)
    {
        $this->fallbackLiveRepo = $fallbackLiveRepo;
    }

    public function getTargetProgress(int $trackingCodeId, int $categoryId): array
    {
        // Target progress calculations are computed on-the-fly for real-time accuracy
        return $this->fallbackLiveRepo->getTargetProgress($trackingCodeId, $categoryId);
    }

    public function getMatrixProgress(int $trackingCodeId): array
    {
        return $this->fallbackLiveRepo->getMatrixProgress($trackingCodeId);
    }

    public function getLifecycleSummary(Initiative $initiative): array
    {
        // The legacy cache has no per-line fulfilment or inventory scope. Never serve its
        // aggregate ratio, and do not dispatch a write from a monitoring GET.
        return $this->fallbackLiveRepo->getLifecycleSummary($initiative);
    }
}
