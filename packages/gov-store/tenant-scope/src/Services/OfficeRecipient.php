<?php

namespace GovStore\TenantScope\Services;

use App\Models\User;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Contracts\MembershipContextResolver;

/** Resolve custody recipients without widening the global user scope. */
class OfficeRecipient
{
    public function find(int $id): User
    {
        $context = app(TenantContext::class);
        $user = User::withoutGlobalScopes()->whereNull('deleted_at')->where('activated', true)->findOrFail($id);
        abort_unless($context->locationId && $context->companyId
            && (int) $user->company_id === $context->companyId
            && app(MembershipContextResolver::class)->hasActiveMembershipAt($id, $context->locationId), 404);

        return $user;
    }
}
