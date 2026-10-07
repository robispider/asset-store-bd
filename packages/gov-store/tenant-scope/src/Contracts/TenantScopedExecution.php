<?php

namespace GovStore\TenantScope\Contracts;

interface TenantScopedExecution
{
    /** Actor, target type/ID and declared GovStore ability; never a serialized context. */
    public function tenantExecution(): array;
}
