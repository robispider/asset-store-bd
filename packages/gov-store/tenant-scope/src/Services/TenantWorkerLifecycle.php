<?php

namespace GovStore\TenantScope\Services;

use GovStore\TenantScope\Contexts\TenantContext;

class TenantWorkerLifecycle
{
    private array $frames = [];

    public function begin(bool $failClosed = true, bool $preserveContext = false): void
    {
        $context = app(TenantContext::class);
        $this->frames[] = ! $preserveContext && app()->runningInConsole() ? null : [clone $context, auth()->user()];
        $context->reset();
        $context->isActive = $failClosed;
        $context->allowedLocationIds = [];
        $context->allowedCompanyIds = [];
        auth()->forgetUser();
    }

    public function finish(): void
    {
        $context = app(TenantContext::class);
        $context->reset();
        auth()->forgetUser();
        $frame = array_pop($this->frames);
        if ($frame) {
            foreach (get_object_vars($frame[0]) as $property => $value) {
                $context->$property = $value;
            }
            if ($frame[1]) {
                auth()->setUser($frame[1]);
            }
        }
    }
}
