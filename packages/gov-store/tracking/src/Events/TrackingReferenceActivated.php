<?php

namespace GovStore\Tracking\Events;

use GovStore\Tracking\Models\TrackingReference;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TrackingReferenceActivated
{
    use Dispatchable, SerializesModels;

    public TrackingReference $reference;

    public function __construct(TrackingReference $reference)
    {
        $this->reference = $reference;
    }
}