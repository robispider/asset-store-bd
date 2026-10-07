<?php

namespace GovStore\TenantScope\Services;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Contracts\OrganizationContextResolver;
use App\Models\User;

class BoundaryResolver
{
    protected TenantContext $context;
    public function __construct(TenantContext $context)
    {
        $this->context = $context;
    }

    /**
     * Resolves the active scoping boundary strategy.
     * Returns: 'global', 'company', 'location', or 'jurisdiction'.
     */
    public function resolveStrategy(User $user, ?string $referenceType = null): string
    {
        // 1. Superadmins bypass strict local scopes unless acting in an office context
        if ($user->isSuperUser() && $this->context->isGlobal) {
            return 'global';
        }

        // 2. Check if reference type specifies custom config override
        if ($referenceType) {
            $config = $this->context->getConfig($referenceType);
            if ($config && $config->scope_strategy === 'global') {
                return 'global';
            }
        }

        // 3. ICT Officers operate on a geographic boundary strategy
        $isIctOfficer = app(OrganizationContextResolver::class)->jurisdictionLocations((int) $user->id) !== null;
        if ($isIctOfficer) {
            return 'jurisdiction';
        }

        // 4. Fallback to standard context-driven isolation
        if ($this->context->isActive) {
            return $this->context->isHomeOffice ? 'location' : 'company';
        }

        return 'location';
    }
}
