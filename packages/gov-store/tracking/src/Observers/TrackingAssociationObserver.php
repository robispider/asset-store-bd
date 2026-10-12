<?php

namespace GovStore\Tracking\Observers;

use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Services\ProjectionRefresh;

class TrackingAssociationObserver
{
    public function saved(TrackingAssociation $association): void
    {
        ProjectionRefresh::codes([$association->tracking_code_id, $association->getOriginal('tracking_code_id')]);
    }

    public function deleted(TrackingAssociation $association): void
    {
        ProjectionRefresh::codes([$association->tracking_code_id]);
    }
}
