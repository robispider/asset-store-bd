<?php

namespace GovStore\TenantScope\Policies;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ReferenceOwnershipService;

class ReferenceBoundaryPolicy
{
    protected ReferenceOwnershipService $ownershipService;

    public function __construct(ReferenceOwnershipService $ownershipService)
    {
        $this->ownershipService = $ownershipService;
    }

    /**
     * Evaluates if the current tenant owns this shared catalog record.
     */
    public function canMutate(Model $model, TenantContext $context): bool
    {
        $state = $this->ownershipService->getOwnershipState($model);
        $ownerId = $this->ownershipService->getOwnerId($model);

        if ($state === 'GLOBAL') {
            // Global items belong to the State/Country. 
            // Local Office Admins can USE them, but CANNOT MUTATE (Edit/Delete) them.
            return false;
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