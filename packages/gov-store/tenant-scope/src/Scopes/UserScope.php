<?php

namespace GovStore\TenantScope\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Auth\SessionGuard;
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

                if ($context->locationId && \Illuminate\Support\Facades\Schema::hasTable('gov_office_memberships')) {
                    $hasExpiry = app(\GovStore\TenantScope\Services\SchemaKnowledge::class)
                        ->hasColumn(new \GovStore\OfficeMembership\Models\OfficeMembership, 'valid_until');
                    $query->orWhere(function ($members) use ($context, $table, $hasExpiry) {
                        $members->where($table.'.activated', true)->where($table.'.company_id', $context->companyId)
                            ->whereExists(function ($sub) use ($context, $table, $hasExpiry) {
                                $sub->selectRaw('1')->from('gov_office_memberships as membership')
                                    ->whereColumn('membership.user_id', $table.'.id')
                                    ->where('membership.location_id', $context->locationId)->where('membership.status', 'active');
                                if ($hasExpiry) {
                                    $sub->where(fn ($q) => $q->whereNull('membership.valid_until')->orWhereDate('membership.valid_until', '>=', today()));
                                }
                            });
                    });
                }

                // =========================================================================
                // THE BULLETPROOF IDENTITY BYPASS:
                // Regardless of location mapping, a user MUST always be able to retrieve 
                // their own row from the database to prevent login redirect loops.
                // =========================================================================
                if ($authId = $this->authenticatedIdWithoutResolving()) {
                    $query->orWhere($table . '.id', $authId);
                }
            });
        }
    }

    // auth()->id() would load the user through this same scope and recurse forever.
    private function authenticatedIdWithoutResolving(): mixed
    {
        $guard = auth()->guard();

        if ($guard->hasUser()) {
            return $guard->id();
        }

        if ($guard instanceof SessionGuard) {
            return $guard->getSession()->get($guard->getName());
        }

        return null;
    }
}
