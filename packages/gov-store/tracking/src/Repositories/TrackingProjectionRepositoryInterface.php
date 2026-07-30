<?php

namespace GovStore\Tracking\Repositories;

use GovStore\Tracking\Models\Initiative;

interface TrackingProjectionRepositoryInterface
{
    /**
     * Compute and compile the lifecycle state metrics for a given Initiative umbrella.
     */
    public function getLifecycleSummary(Initiative $initiative): array;

    /**
     * Compute the mathematical received-progress of a single quantitative target.
     */
    public function getTargetProgress(int $trackingCodeId, int $categoryId): array;

    /**
     * Compile location-specific allocations and received progress for a Matrix-type Tracking Code.
     */
    public function getMatrixProgress(int $trackingCodeId): array;
}