<?php

namespace GovStore\TenantScope\Models;

use Illuminate\Database\Eloquent\Model;

class TenantScopeMapping extends Model
{
    protected $table = 'gov_tenant_scope_mappings';

    protected $fillable = [
        'scope_type',
        'scope_id',
        'reference_type',
        'reference_id',
    ];

    /**
     * Polymorphic relation to who owns this reference (Company/Ministry or Location/Office)
     */
    public function scopeTarget()
    {
        return $this->morphTo('scope', 'scope_type', 'scope_id');
    }
}