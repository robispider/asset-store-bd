<?php

namespace GovStore\Tracking\Services;

use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;

/**
 * Names shown in the tracking breadcrumbs. They come only from tracking views that actually rendered,
 * so an error or "no access" page never reveals a programme or code name through its trail; there the
 * breadcrumb falls back to a generic label.
 */
class TrackingTrail
{
    private ?string $programme = null;

    private ?string $code = null;

    public function remember(array $data): void
    {
        if (($data['initiative'] ?? null) instanceof Initiative) {
            $this->programme = $data['initiative']->title;
        }
        if (($data['trackingCode'] ?? null) instanceof TrackingCode) {
            $this->code = $data['trackingCode']->tracking_code;
        }
    }

    public function programme(): string
    {
        return $this->programme ?? __('govtracking::general.crumbs.programme');
    }

    public function code(): string
    {
        return $this->code ?? __('govtracking::general.crumbs.code');
    }
}
