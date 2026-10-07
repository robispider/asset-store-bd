<?php

namespace GovStore\StoreOperations\Integrations\Tracking;

use GovStore\StoreOperations\Contracts\TrackingCodeVerifier;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Services\ScopeValidatorService;

class TrackingCodeVerifierAdapter implements TrackingCodeVerifier
{
    public function __construct(private ScopeValidatorService $scopeValidator) {}

    public function failureReason(string $code, int $locationId): ?string
    {
        $trackingCode = TrackingCode::with(['initiative' => fn ($query) => $query->withoutGlobalScopes()])
            ->where('tracking_code', $code)->where('status', 'ACTIVE')->first();
        if (! $trackingCode) {
            return __('storeops::storeops.tracking_code_invalid');
        }

        if (! $trackingCode->initiative || $trackingCode->initiative->status !== 'Active') {
            return __('storeops::storeops.tracking_initiative_inactive');
        }

        $result = $this->scopeValidator->validateExecutionScope($trackingCode, $locationId);

        return $result['is_valid'] ? null : ($result['message'] ?: __('storeops::storeops.tracking_code_invalid'));
    }
}
