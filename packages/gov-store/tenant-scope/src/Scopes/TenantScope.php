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

        // --- LOCATION HIERARCHY SCOPING (Strict physical boundary constraint) ---
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

        // --- REFERENCE CATALOG SCOPING (Categories, Models, Suppliers, etc.) ---
        $referenceSingular = Str::singular($this->referenceType);

        // Intelligently enforce Isolation without relying on external config tables
        $builder->where(function ($query) use ($context, $referenceSingular, $keyName) {
            
            // CONDITION A: The item is explicitly adopted by the user's active company or location
            $query->whereIn($keyName, function ($subQuery) use ($context, $referenceSingular) {
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular)
                    ->where('is_active', 1) // Must be operationally active to appear in dropdowns
                    ->where(function ($q) use ($context) {
                        // Match Company Scope
                        if ($context->companyId > 0) {
                            $q->where('scope_type', 'company')->where('scope_id', $context->companyId);
                        }
                        // Match Location Scope (For standalone offices)
                        if ($context->locationId > 0) {
                            $q->orWhere(function ($sq) use ($context) {
                                $sq->where('scope_type', 'location')->where('scope_id', $context->locationId);
                            });
                        }
                    });
            })
            // CONDITION B: The item is completely unadopted by ANYONE (Shared Government Standard)
            ->orWhereNotIn($keyName, function ($subQuery) use ($referenceSingular) {
                // By selecting ALL rows (active or archived), we guarantee that if an item 
                // belongs to another organization, it is strictly hidden from this user.
                $subQuery->select('reference_id')
                    ->from('gov_tenant_scope_mappings')
                    ->where('reference_type', $referenceSingular);
            });
            
        });
    }
}