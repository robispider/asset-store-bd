<?php

namespace GovStore\CustomRequests\Services;

use App\Models\User;
use GovStore\CustomRequests\Models\Request;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\AccessAudit;
use GovStore\TenantScope\Services\GovAccess;

class RequestAccess
{
    public function __construct(private TenantContext $context, private GovAccess $access) {}

    public function check(Request $request, User $actor, string $ability): void
    {
        // Unresolved historical offices are quarantined, including for superusers.
        abort_unless($request->office_id && ($actor->isSuperUser()
            || (int) $request->office_id === $this->context->locationId), 404);
        $decision = $this->access->decide($actor, $ability);
        abort_unless($this->access->permitsRequest($actor, $ability), 403);
        if (! $decision->allowed) {
            app(AccessAudit::class)->record($decision, 'shadow');
        }
    }
}
