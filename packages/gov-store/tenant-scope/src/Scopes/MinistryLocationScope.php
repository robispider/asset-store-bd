<?php

namespace GovStore\TenantScope\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use GovStore\TenantScope\Contexts\TenantContext;

class MinistryLocationScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        // Fail safely if context is not bound (e.g. running in console without middleware)
        if (!app()->bound(TenantContext::class)) return;
        
        $context = app(TenantContext::class);

        // If context is inactive (Superadmin or Guest), bypass filtering
        if (!$context->isActive) return;

        $table = $model->getTable();

        if ($context->companyId) {
            $builder->where($table . '.company_id', $context->companyId);
        }

        if ($context->locationId) {
            $builder->where($table . '.location_id', $context->locationId);
        } else {
            $builder->whereRaw('1 = 0');
        }
    }
}