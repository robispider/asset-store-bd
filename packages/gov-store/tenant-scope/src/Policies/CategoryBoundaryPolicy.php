<?php

namespace GovStore\TenantScope\Policies;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\ReferenceOwnershipService;

class CategoryBoundaryPolicy
{
    public array $relationMap = ['category_id' => \App\Models\Category::class, 'manufacturer_id' => \App\Models\Manufacturer::class];
    protected ReferenceOwnershipService $ownershipService;

    public function __construct(ReferenceOwnershipService $ownershipService)
    {
        $this->ownershipService = $ownershipService;
    }

    public function canMutate(Model $model, TenantContext $context): bool
    {
        $state = $this->ownershipService->getOwnershipState($model);
        $ownerId = $this->ownershipService->getOwnerId($model);
        $user = auth()->user();
        if (! $user) {
            return false;
        }
        $roles = app(\GovStore\TenantScope\Services\GovAccess::class)->roles($user);

        if ($state === 'GLOBAL') {
            return $user->isSuperUser() && request()->attributes->get('gov_national_reviewed') === 'catalog.master.manage';
        }

        if ($state === 'COMPANY') {
            return $ownerId === $context->companyId && in_array('company_admin', $roles, true);
        }

        if ($state === 'LOCATION') {
            return $ownerId === $context->locationId
                && (bool) array_intersect($roles, ['office_admin', 'storekeeper', 'catalog_maintainer']);
        }

        return false;
    }
}
