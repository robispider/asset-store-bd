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
        // Fail safely if context is not bound (e.g. CLI operations)
        if (!app()->bound(TenantContext::class)) {
            return;
        }
        
        $context = app(TenantContext::class);

        // If context is inactive (Superadmin, Guest, or Background System Jobs), bypass checks
        if (!$context->isActive) {
            return;
        }

        $table = $model->getTable();

        // 1. Enforce Ministry (Company) Scoping if column exists
        if ($context->companyId && Schema::hasColumn($table, 'company_id')) {
            $builder->where($table . '.company_id', $context->companyId);
        }

        // 2. Enforce Physical Office (Location) Scoping if column exists
        if (Schema::hasColumn($table, 'location_id')) {
            if ($context->locationId) {
                $builder->where($table . '.location_id', $context->locationId);
            } else {
                // Prevent unassigned standard accounts from leaking records
                $builder->whereRaw('1 = 0');
            }
        }
    }
}