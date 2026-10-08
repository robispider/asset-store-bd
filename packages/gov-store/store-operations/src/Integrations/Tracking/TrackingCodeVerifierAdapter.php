<?php

namespace GovStore\StoreOperations\Integrations\Tracking;

use GovStore\StoreOperations\Contracts\TrackingCodeVerifier;
use GovStore\Tracking\Services\ProgrammeVerifier;
use Illuminate\Validation\ValidationException;

class TrackingCodeVerifierAdapter implements TrackingCodeVerifier
{
    public function __construct(private ProgrammeVerifier $verifier) {}

    public function failureReason(string $code, int $locationId): ?string
    {
        try {
            $this->verifier->resolve($code, $locationId);
            return null;
        } catch (ValidationException $e) {
            return collect($e->errors())->flatten()->first();
        }
    }
}
