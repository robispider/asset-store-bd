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
        // 1. Fail safely if context is not bound (e.g. CLI or pre-boot operations)
        if (!app()->bound(TenantContext::class)) {
            return;
        }
        
        $context = app(TenantContext::class);

        // 2. Bypass isolation entirely if inactive or acting as Global Superadmin
        if (!$context->isActive || $context->isGlobal) {
            return;
        }

        $table = $model->getTable();

        // 3. Apply the pure, pre-computed hierarchical boundaries
        if (is_array($context->allowedLocationIds)) {
            
            // If the array is explicitly empty, the user has zero permitted visibility
            if (empty($context->allowedLocationIds)) {
                $builder->whereRaw('1 = 0');
            } else {
                // Safely isolate the data to the allowed location bounds
                $builder->where(function ($query) use ($table, $context) {
                    $query->whereIn($table . '.location_id', $context->allowedLocationIds);
                    
                    // =========================================================================
                    // THE BULLETPROOF IDENTITY BYPASS:
                    // Guaranteed fail-safe preventing login redirect loops. 
                    // An authenticated user must ALWAYS be able to retrieve their own record.
                    // =========================================================================
                    if ($authId = auth()->id()) {
                        $query->orWhere($table . '.id', $authId);
                    }
                });
            }
        }
    }
}