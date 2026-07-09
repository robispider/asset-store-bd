<?php

namespace GovStore\TenantScope\Policies;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use GovStore\TenantScope\Contexts\TenantContext;

class TransactionalBoundaryPolicy
{
    /**
     * Evaluates if the current tenant owns this physical/operational record.
     */
    public function canMutate(Model $model, TenantContext $context): bool
    {
        $table = $model->getTable();

        // Check Company Ownership (Ministry)
        if (Schema::hasColumn($table, 'company_id')) {
            if ($model->company_id !== $context->companyId) {
                return false;
            }
        }

        // Check Location Ownership (Physical Office)
        if (Schema::hasColumn($table, 'location_id')) {
            if ($model->location_id !== $context->locationId) {
                return false;
            }
        }

        return true;
    }
}