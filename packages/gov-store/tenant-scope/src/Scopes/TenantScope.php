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
        
        // 1. Global Bypass: Superadmins in Global Overview see everything
        if (!$context->isActive || $context->isGlobal) {
            return;
        }

        $table = $model->getTable();
        $keyName = $table . '.' . $model->getKeyName();

        // --- LOCATION HIERARCHY SCOPING ---
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

        
   // --- COMPANY (MINISTRY) HIERARCHY SCOPING ---
        if ($this->referenceType === 'companies') {
            // If allowedCompanyIds is null, it means the user (e.g. ICT Officer) is allowed to see all companies.
            if ($context->allowedCompanyIds === null) {
                return; 
            }
            
            // If it is an array, they are restricted.
            if (is_array($context->allowedCompanyIds)) {
                if (empty($context->allowedCompanyIds)) {
                    $builder->whereRaw('1 = 0'); // Standalone users see no companies
                } else {
                    $builder->whereIn($table . '.id', $context->allowedCompanyIds);
                }
            }
            return;
        }

        // --- REFERENCE CATALOG SCOPING (Categories, Models, Suppliers, etc.) ---
        $referenceSingular = Str::singular($this->referenceType);

        $builder->where(function ($query) use ($context, $referenceSingular, $keyName) {
            
            // CONDITION A: Item explicitly adopted by active context
            $query->whereIn($keyName, function ($subQuery) use ($context, $referenceSingular) {
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular)
                    ->where('is_active', 1) 
                    ->where(function ($q) use ($context) {
                        if ($context->companyId > 0) {
                            $q->where('scope_type', 'company')->where('scope_id', $context->companyId);
                        }
                        if ($context->locationId > 0) {
                            $q->orWhere(function ($sq) use ($context) {
                                $sq->where('scope_type', 'location')->where('scope_id', $context->locationId);
                            });
                        }
                    });
            })
            // CONDITION B: Shared Government Standard (Unadopted completely)
            ->orWhereNotIn($keyName, function ($subQuery) use ($referenceSingular) {
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular);
            });
            
        });
    }
}