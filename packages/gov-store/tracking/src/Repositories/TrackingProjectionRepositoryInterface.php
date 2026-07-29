<?php

namespace GovStore\Tracking\Repositories;

use GovStore\Tracking\Models\TrackingReference;

interface TrackingProjectionRepositoryInterface
{
    /**
     * Compute and compile the lifecycle state metrics for a given tracking reference.
     * Return structure: ['planned' => X, 'ordered' => Y, 'received' => Z, 'deployed' => W, 'disposed' => V]
     */
    public function getLifecycleSummary(TrackingReference $reference): array;
}
