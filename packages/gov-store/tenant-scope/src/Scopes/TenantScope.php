<?php

namespace GovStore\TenantScope\Scopes;

use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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
        if (!$context->isActive) return;

        $config = $context->getConfig($this->referenceType);
        
        if (!$config || $config->scope_strategy === 'global') {
            return;
        }

        $strategy = $config->scope_strategy;
        $referenceSingular = str_singular($this->referenceType);
        $keyName = $model->getTable() . '.' . $model->getKeyName();

        // STAGE 2: Tenant Alignment Join (Using Raw DB to prevent Eloquent looping)
        $builder->where(function ($query) use ($context, $strategy, $referenceSingular, $keyName) {
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
            ->orWhereNotIn($keyName, function ($subQuery) use ($referenceSingular) {
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular);
            });
        });

        // STAGE 3: Show Only Used (Replaced whereHas with safe SQL EXISTS to prevent cross-model recursion)
        if ($config->show_only_used) {
            $targetCol = $referenceSingular . '_id'; // e.g. category_id, model_id
            
            // Only execute if this column actually exists on the assets table
            if (in_array($targetCol, ['category_id', 'model_id', 'supplier_id', 'manufacturer_id', 'location_id'])) {
                $existsQuery = DB::table('assets')
                    ->whereColumn('assets.' . $targetCol, $keyName)
                    ->whereNull('deleted_at'); // Exclude soft deletes
                
                if ($strategy === 'company' && $context->companyId) {
                    $existsQuery->where('company_id', $context->companyId);
                } elseif ($strategy === 'office' && $context->locationId) {
                    $existsQuery->where('location_id', $context->locationId);
                }
                
                $builder->whereExists($existsQuery);
            }
        }
    }
}