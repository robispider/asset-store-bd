<?php

namespace GovStore\Tracking\Repositories;

use GovStore\Tracking\Models\Initiative;

interface TrackingProjectionRepositoryInterface
{
    /**
     * Compute and compile the lifecycle state metrics for a given Initiative umbrella.
     */
    public function getLifecycleSummary(Initiative $initiative): array;
}