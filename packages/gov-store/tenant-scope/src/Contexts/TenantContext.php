<?php

namespace GovStore\TenantScope\Contexts;

class TenantContext
{
    public bool $isActive = false;
    public bool $isGlobal = false; // True for Superadmins
    
    public ?array $allowedLocationIds = null; // Hierarchy bounds for viewing users/offices
    
    public ?int $membershipId = null;
    public ?int $companyId = null;
    public ?int $locationId = null; // Active operational working context
    public bool $isHomeOffice = false;

    public array $configs = [];

    public function getConfig(string $referenceType): ?object
    {
        return $this->configs[$referenceType] ?? null;
    }
}