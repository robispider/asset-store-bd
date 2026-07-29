<?php

namespace GovStore\Metadata\Events;

use App\Models\AssetModel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssetModelCreated
{
    use Dispatchable, SerializesModels;

    public AssetModel $assetModel;

    public function __construct(AssetModel $assetModel)
    {
        $this->assetModel = $assetModel;
    }
}
