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

        // An unadopted reference is shared only within its actual ownership boundary.
        // Absence of an adoption must never publish an explicitly private definition.
        if (\Illuminate\Support\Facades\Schema::hasTable('gov_reference_owners')) {
            $builder->where(function ($visibility) use ($context, $referenceSingular, $keyName) {
                $visibility->whereNotExists(function ($owner) use ($referenceSingular, $keyName) {
                    $owner->selectRaw('1')->from('gov_reference_owners as owner')->where('owner.reference_type', $referenceSingular)
                        ->whereColumn('owner.reference_id', $keyName);
                })->orWhereExists(function ($owner) use ($context, $referenceSingular, $keyName) {
                    $owner->selectRaw('1')->from('gov_reference_owners as owner')->where('owner.reference_type', $referenceSingular)
                        ->whereColumn('owner.reference_id', $keyName)->where(function ($scope) use ($context) {
                            $scope->where('owner.owner_type', 'national')
                                ->orWhere(fn ($company) => $company->where('owner.owner_type', 'company')->whereNotNull('owner.owner_id')->where('owner.owner_id', $context->companyId))
                                ->orWhere(function ($office) use ($context) {
                                    $office->where('owner.owner_type', 'office')->whereExists(function ($location) use ($context) {
                                        $location->selectRaw('1')->from('locations as owner_office')->whereColumn('owner_office.id', 'owner.owner_id')
                                            ->whereNotNull('owner_office.company_id')->where('owner_office.company_id', $context->companyId)->whereNull('owner_office.deleted_at');
                                    });
                                })->orWhere(function ($unknown) use ($context) {
                                    $unknown->where('owner.owner_type', 'unresolved')->whereExists(function ($adoption) use ($context) {
                                        $adoption->selectRaw('1')->from('gov_tenant_scope_mappings as visibility_adoption')
                                            ->whereColumn('visibility_adoption.reference_id', 'owner.reference_id')
                                            ->whereColumn('visibility_adoption.reference_type', 'owner.reference_type')->where('visibility_adoption.is_active', true)
                                            ->where(fn ($q) => $q->where(fn ($q) => $q->where('visibility_adoption.scope_type', 'company')->where('visibility_adoption.scope_id', $context->companyId))
                                                ->orWhere(fn ($q) => $q->where('visibility_adoption.scope_type', 'location')->where('visibility_adoption.scope_id', $context->locationId)));
                                    });
                                });
                        });
                });
            });
        }

        $builder->where(function ($query) use ($context, $referenceSingular, $keyName) {
            
            // CONDITION A: Item explicitly adopted by active context
            $query->whereIn($keyName, function ($subQuery) use ($context, $referenceSingular) {
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular)
                    ->where('is_active', 1) 
                    ->where(function ($q) use ($context) {
                        if (! $context->companyId && ! $context->locationId) {
                            $q->whereRaw('1 = 0');
                        }
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
