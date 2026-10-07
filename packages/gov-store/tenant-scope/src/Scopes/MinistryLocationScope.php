<?php

namespace GovStore\TenantScope\Scopes;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\SchemaKnowledge;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class MinistryLocationScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $context = app(TenantContext::class);
        if (! $context->isActive || $context->isGlobal) {
            return;
        }
        $schema = app(SchemaKnowledge::class);
        $table = $model->getTable();
        $locations = $context->allowedInventoryLocationIds ?? ($context->isCompanyAdmin
            ? $context->allowedLocationIds : ($context->locationId ? [$context->locationId] : []));
        $companies = $context->allowedInventoryCompanyIds ?? $context->allowedCompanyIds ?? ($context->companyId ? [$context->companyId] : []);
        if ($schema->hasColumn($model, 'location_id')) {
            $builder->whereIn($table.'.location_id', $locations ?? []);
            if ($schema->hasColumn($model, 'company_id')) {
                $builder->whereIn($table.'.company_id', $companies);
            }
        } elseif ($schema->hasColumn($model, 'company_id')) {
            // Native licenses have company ownership, never jurisdiction ownership.
            $builder->whereIn($table.'.company_id', $companies);
        } else {
            $builder->whereRaw('1 = 0');
        }
    }
}
