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

    public function apply(Builder $builder, Model $model)
    {
        if (!app()->bound(TenantContext::class)) return;
        
        $context = app(TenantContext::class);
        
        // If inactive or global admin, no restrictions
        if (!$context->isActive || $context->isGlobal) {
            return;
        }

        $table = $model->getTable();
        $keyName = $table . '.' . $model->getKeyName();

        // --- LOCATION HIERARCHY ---
        if ($this->referenceType === 'locations') {
            if (is_array($context->allowedLocationIds)) {
                if (empty($context->allowedLocationIds)) {
                    $builder->whereRaw('1 = 0');
                } else {
                    $builder->whereIn($table . '.id', $context->allowedLocationIds);
                }
            }
            return;
        }

     
        // --- CATALOG MAPPING ---
        $config = $context->getConfig($this->referenceType);
        if (!$config || $config->scope_strategy === 'global') return;

        $strategy = $config->scope_strategy;
        $referenceSingular = Str::singular($this->referenceType);

        $builder->where(function ($query) use ($context, $strategy, $referenceSingular, $keyName) {
            $query->whereIn($keyName, function ($subQuery) use ($context, $strategy, $referenceSingular) {
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular)
                    ->where('is_active', true); // Fixed: Only show operationally active adoptions
                
                if ($strategy === 'company') {
                    $subQuery->where('scope_type', 'company')->where('scope_id', $context->companyId);
                } else {
                    $subQuery->where('scope_type', 'location')->where('scope_id', $context->locationId);
                }
            })
            ->orWhereNotIn($keyName, function ($subQuery) use ($referenceSingular) {
                // If it is completely unmapped, it acts as a global standard
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular)
                    ->where('is_active', true);
            });
        });
    }
}