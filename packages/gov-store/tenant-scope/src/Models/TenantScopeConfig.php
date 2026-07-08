<?php

namespace GovStore\TenantScope\Models;

use Illuminate\Database\Eloquent\Model;

class TenantScopeConfig extends Model
{
    protected $table = 'gov_tenant_scopes';

    protected $fillable = [
        'reference_type',
        'scope_strategy',
        'show_only_used',
    ];

    protected $casts = [
        'show_only_used' => 'boolean',
    ];
}