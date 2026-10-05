<?php

namespace GovStore\StoreOperations\Contracts;

interface TrackingCodeVerifier
{
    /** Return a safe validation message, or null when the code is valid for this office. */
    public function failureReason(string $code, int $locationId): ?string;
}
