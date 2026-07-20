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
        // 1. Fail safely if context is not bound
        if (!app()->bound(TenantContext::class)) {
            return;
        }
        
        $context = app(TenantContext::class);

        // 2. Bypass isolation entirely if inactive or acting as Global Superadmin
        if (!$context->isActive || $context->isGlobal) {
            return;
        }

        $table = $model->getTable();

        // 3. The Pure Isolation Boundary with Bulletproof Self-Bypass
        if (is_array($context->allowedLocationIds)) {
            $builder->where(function ($query) use ($table, $context) {
                
                // Allow them to see users within their allowed hierarchy bounds
                if (!empty($context->allowedLocationIds)) {
                    $query->whereIn($table . '.location_id', $context->allowedLocationIds);
                } else {
                    $query->whereRaw('1 = 0'); // Block all external users if no bounds exist
                }

                // =========================================================================
                // THE BULLETPROOF IDENTITY BYPASS:
                // Regardless of location mapping, a user MUST always be able to retrieve 
                // their own row from the database to prevent login redirect loops.
                // =========================================================================
                if ($authId = auth()->id()) {
                    $query->orWhere($table . '.id', $authId);
                }
            });
        }
    }
}