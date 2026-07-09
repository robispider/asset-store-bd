<?php

namespace GovStore\TenantScope\Services;

use GovStore\TenantScope\Models\TenantScopeMapping;
use Illuminate\Database\Eloquent\Model;

class ReferenceOwnershipService
{
    /**
     * Resolves the polymorphic mapping for a catalog reference item.
     */
    public function getMapping(Model $model): ?TenantScopeMapping
    {
        $refType = strtolower(class_basename($model));
        return TenantScopeMapping::where('reference_type', $refType)
            ->where('reference_id', $model->getKey())
            ->first();
    }

    /**
     * Returns GLOBAL, COMPANY, or OFFICE based on its mapping state.
     */
    public function getOwnershipState(Model $model): string
    {
        $mapping = $this->getMapping($model);
        
        if (!$mapping) {
            return 'GLOBAL';
        }

        return strtoupper($mapping->scope_type); // Returns 'COMPANY' or 'LOCATION' (OFFICE)
    }

    /**
     * Returns the specific ID of the owning Company or Location.
     */
    public function getOwnerId(Model $model): ?int
    {
        $mapping = $this->getMapping($model);
        return $mapping ? $mapping->scope_id : null;
    }
}