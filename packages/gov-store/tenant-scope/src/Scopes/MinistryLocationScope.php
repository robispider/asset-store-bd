<?php

namespace GovStore\TenantScope\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;
use GovStore\TenantScope\Contexts\TenantContext;

class MinistryLocationScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (!app()->bound(TenantContext::class)) {
            return;
        }
        
        $context = app(TenantContext::class);

        // If inactive (Superadmin global view), bypass
        if (!$context->isActive) {
            return;
        }

        $table = $model->getTable();

        if ($context->companyId && Schema::hasColumn($table, 'company_id')) {
            $builder->where($table . '.company_id', $context->companyId);
        }

        if (Schema::hasColumn($table, 'location_id')) {
            if ($context->locationId) {
                $builder->where($table . '.location_id', $context->locationId);
            } else {
                $builder->whereRaw('1 = 0');
            }
        }
    }
}