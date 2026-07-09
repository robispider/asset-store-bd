<?php

namespace GovStore\TenantScope\Policies;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ReferenceOwnershipService;

class CategoryBoundaryPolicy
{
    protected ReferenceOwnershipService $ownershipService;

    public function __construct(ReferenceOwnershipService $ownershipService)
    {
        $this->ownershipService = $ownershipService;
    }

    public function canMutate(Model $model, TenantContext $context): bool
    {
        $state = $this->ownershipService->getOwnershipState($model);
        $ownerId = $this->ownershipService->getOwnerId($model);

        if ($state === 'GLOBAL') {
            return false; // Only global Superadmins can mutate Global reference templates
        }

        if ($state === 'COMPANY') {
            return $ownerId === $context->companyId;
        }

        if ($state === 'LOCATION') {
            return $ownerId === $context->locationId;
        }

        return false;
    }
}