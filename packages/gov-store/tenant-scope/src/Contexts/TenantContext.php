<?php

namespace GovStore\TenantScope\Contexts;

use GovStore\TenantScope\Services\EffectivePermissionSet;

class TenantContext
{
    public bool $isActive = false;
    public bool $isGlobal = false; // True for Superadmins
    
    public ?array $allowedLocationIds = null; // Pre-computed hierarchy bounds for viewing users/offices
    
    public ?int $membershipId = null;
    public ?int $companyId = null;
    public ?int $locationId = null; // Active operational working context
    public bool $isHomeOffice = false;

    // Cache for the active request's EffectivePermissionSet
    public ?EffectivePermissionSet $effectivePermissions = null;

    public array $configs = [];

    /**
     * Safely retrieves the cached configuration for a specific reference type.
     */
    public function getConfig(string $referenceType): ?object
    {
        return $this->configs[$referenceType] ?? null;
    }
}