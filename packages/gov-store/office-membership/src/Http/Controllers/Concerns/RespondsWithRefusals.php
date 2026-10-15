<?php

namespace GovStore\OfficeMembership\Http\Controllers\Concerns;

use GovStore\TenantScope\Http\Controllers\Concerns\RespondsWithRefusals as SharedRefusals;

/**
 * Workflow refusals become a message under the field, with the person's input kept (plan F1).
 * The behaviour lives in tenant-scope so custom-requests uses the same concern (D8); membership
 * refusals that name no field are shown under 'membership'.
 */
trait RespondsWithRefusals
{
    use SharedRefusals;

    protected function refusalFallbackField(): string
    {
        return 'membership';
    }
}
