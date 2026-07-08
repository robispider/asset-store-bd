<?php

namespace GovStore\TenantScope\Contexts;

class TenantContext
{
    public bool $isActive = false;
    public ?int $companyId = null;
    public ?int $locationId = null;
    public array $configs = [];

    /**
     * Retrieves the loaded configuration for a specific reference type.
     */
    public function getConfig(string $referenceType): ?object
    {
        return $this->configs[$referenceType] ?? null;
    }
}