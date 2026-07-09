<?php

namespace GovStore\TenantScope\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use GovStore\TenantScope\Contexts\TenantContext;

class TenantScope implements Scope
{
    protected string $referenceType;

    public function __construct(string $referenceType)
    {
        $this->referenceType = $referenceType;
    }

    /**
     * Intercepts queries targeting core reference models and appends strict 
     * tenant mapping and office building isolation rules.
     */
    public function apply(Builder $builder, Model $model)
    {
        if (!app()->bound(TenantContext::class)) {
            return;
        }
        
        $context = app(TenantContext::class);
        if (!$context->isActive) {
            return;
        }

        $keyName = $model->getTable() . '.' . $model->getKeyName();

        // =========================================================================
        // SPECIAL RULE: Standard Office Admins and Employees can ONLY see their 
        // own assigned home office building location.
        // =========================================================================
        if ($this->referenceType === 'locations') {
            if ($context->locationId) {
                $builder->where($model->getTable() . '.id', $context->locationId);
            } else {
                // Safeguard against unassigned user profiles
                $builder->whereRaw('1 = 0');
            }
            return; // Skip reference joins entirely for Locations
        }

        // =========================================================================
        // REFERENCE CATALOGS (Categories, Models, Suppliers, Manufacturers)
        // Restricts catalogs strictly based on Ministry or Office boundary maps
        // =========================================================================
        $config = $context->getConfig($this->referenceType);
        if (!$config || $config->scope_strategy === 'global') {
            return;
        }

        $strategy = $config->scope_strategy;
        $referenceSingular = Str::singular($this->referenceType);

        $builder->where(function ($query) use ($context, $strategy, $referenceSingular, $keyName) {
            
            // Subquery: Permit records mapped to the user's active Ministry/Location
            $query->whereIn($keyName, function ($subQuery) use ($context, $strategy, $referenceSingular) {
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular);
                    
                if ($strategy === 'company') {
                    $subQuery->where('scope_type', 'company')->where('scope_id', $context->companyId);
                } else {
                    $subQuery->where('scope_type', 'location')->where('scope_id', $context->locationId);
                }
            })
            // Fallback: Permit completely unmapped reference records globally by default
            ->orWhereNotIn($keyName, function ($subQuery) use ($referenceSingular) {
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular);
            });
        });
    }
}