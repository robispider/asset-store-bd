<?php

namespace GovStore\Committee\Scopes;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;

/** Explicit HTTP read boundary; trusted in-process contracts do not use this filter. */
class CommitteeBoundaryScope
{
    public function __construct(private TenantContext $context, private GovAccess $access) {}

    public function apply($query)
    {
        $roles = $this->access->roles(auth()->user());
        if (in_array('superuser',$roles,true)) { return $query; }
        if (in_array('company_admin',$roles,true)) { return $query->where('owner_company_id',$this->context->companyId ?? 0); }
        return $query->where('owner_company_id',$this->context->companyId ?? 0)->where(function ($q) {
            $q->where('owner_location_id',$this->context->locationId ?? 0)
                ->orWhereHas('scopes',fn ($s) => $s->where('scope_type','office')->where('scope_id',(string)($this->context->locationId ?? 0)));
        });
    }
}
