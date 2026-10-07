<?php

namespace GovStore\StoreOperations\Services;

use GovStore\StoreOperations\Contracts\TrackingCodeVerifier;

class NullTrackingCodeVerifier implements TrackingCodeVerifier
{
    public function failureReason(string $code, int $locationId): ?string
    {
        return __('storeops::storeops.tracking_verifier_unavailable');
    }
}
