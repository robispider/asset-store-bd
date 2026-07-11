<?php

namespace GovStore\TenantScope\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use GovStore\TenantScope\Contexts\TenantContext;

class UserScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        if (!app()->bound(TenantContext::class)) return;
        
        $context = app(TenantContext::class);
        
        // If inactive or global admin, no restrictions
        if (!$context->isActive || $context->isGlobal) {
            return;
        }

        $table = $model->getTable();

        // Apply the pre-computed hierarchy boundaries
        if (is_array($context->allowedLocationIds)) {
            if (empty($context->allowedLocationIds)) {
                $builder->whereRaw('1 = 0');
            } else {
                $builder->whereIn($table . '.location_id', $context->allowedLocationIds);
            }
        }
    }
}