<?php

namespace GovStore\Metadata\Observers;

use App\Models\AssetModel;
use GovStore\Metadata\Events\AssetModelCreated;

class AssetModelObserver
{
    /**
     * Intercepts core AssetModel creation and dispatches our Domain Event.
     */
    public function created(AssetModel $assetModel): void
    {
        event(new AssetModelCreated($assetModel));
    }
}
