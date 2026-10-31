<?php

namespace GovStore\TenantScope\Policies;

use GovStore\Organization\Services\OfficeAdministration;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Database\Eloquent\Model;

class OfficeLocationBoundaryPolicy
{
    public function canMutate(Model $model, TenantContext $context): bool
    {
        if (! auth()->check()) {
            return false;
        }
        $guard = app(OfficeAdministration::class);
        $guard->office($guard->actor((int) auth()->id()), (int) $model->getKey());

        // Reparenting/company moves need their own reviewed consumer migration.
        return ! $model->isDirty(['company_id', 'parent_id']);
    }
}
