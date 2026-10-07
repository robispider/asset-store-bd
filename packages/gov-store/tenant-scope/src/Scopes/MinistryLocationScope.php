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
        if ($schema->hasColumn($model, 'location_id')) {
            $builder->whereIn($table.'.location_id', $context->allowedLocationIds ?? ($context->locationId ? [$context->locationId] : []));
            if ($context->allowedCompanyIds !== null && $schema->hasColumn($model, 'company_id')) {
                $builder->whereIn($table.'.company_id', $context->allowedCompanyIds);
            }
        } elseif ($schema->hasColumn($model, 'company_id')) {
            // Native licenses have no office column: deny cross-ministry jurisdiction reads.
            $builder->whereIn($table.'.company_id', $context->allowedCompanyIds ?? []);
        } else {
            $builder->whereRaw('1 = 0');
        }
    }
}
