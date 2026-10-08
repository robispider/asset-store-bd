<?php

namespace GovStore\Tracking\Observers;

use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\ProjectionRefresh;
use Illuminate\Database\Eloquent\Model;

class ProjectionSourceObserver
{
    public function saved(Model $model): void
    {
        if ($model instanceof TrackingCode) {
            foreach (array_unique(array_filter([$model->initiative_id, $model->getOriginal('initiative_id')])) as $id) {
                ProjectionRefresh::initiative((int) $id);
            }
        } else {
            ProjectionRefresh::codes([$model->tracking_code_id, $model->getOriginal('tracking_code_id')]);
        }
    }

    public function deleted(Model $model): void
    {
        $this->saved($model);
    }
}
