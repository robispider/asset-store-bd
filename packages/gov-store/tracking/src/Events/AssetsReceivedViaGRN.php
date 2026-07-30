<?php

namespace GovStore\Tracking\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AssetsReceivedViaGRN
{
    use Dispatchable, SerializesModels;

    public string $trackingCodeString;
    public array $assetIds;
    public int $actorId;
    public ?string $overrideReason;
    public string $grnReferenceNumber;

    public function __construct(string $trackingCodeString, array $assetIds, int $actorId, string $grnReferenceNumber, ?string $overrideReason = null)
    {
        $this->trackingCodeString = $trackingCodeString;
        $this->assetIds = $assetIds;
        $this->actorId = $actorId;
        $this->grnReferenceNumber = $grnReferenceNumber;
        $this->overrideReason = $overrideReason;
    }
}