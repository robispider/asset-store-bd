<?php

namespace GovStore\Tracking\Observers;

use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Jobs\RebuildTrackingProjectionJob;

class TrackingAssociationObserver
{
    public function saved(TrackingAssociation $association): void
    {
        RebuildTrackingProjectionJob::dispatch($association->tracking_reference_id);
    }

    public function deleted(TrackingAssociation $association): void
    {
        RebuildTrackingProjectionJob::dispatch($association->tracking_reference_id);
    }
}