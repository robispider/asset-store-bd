<?php

namespace GovStore\TenantScope\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ActionFailure
{
    public function message(Throwable $exception): string
    {
        $reference = (string) Str::uuid();
        Log::error('GovStore operation failed', ['reference_id' => $reference, 'exception' => $exception]);

        return __('tenantops::access.failed', ['reference' => $reference]);
    }
}
