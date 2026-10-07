<?php

namespace GovStore\TenantScope\Policies;

use Illuminate\Database\Eloquent\Model;
use GovStore\TenantScope\Services\SchemaKnowledge;
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
        if (app(SchemaKnowledge::class)->hasColumn($model, 'company_id')) {
            if ($model->company_id !== $context->companyId) {
                return false;
            }
        }

        // Check Location Ownership (Physical Office)
        if (app(SchemaKnowledge::class)->hasColumn($model, 'location_id')) {
            if ($model->location_id !== $context->locationId) {
                return false;
            }
        }

        return true;
    }
}
