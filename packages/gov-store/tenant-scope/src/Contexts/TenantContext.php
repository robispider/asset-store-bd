<?php

namespace GovStore\TenantScope\Contexts;

use GovStore\TenantScope\Services\EffectivePermissionSet;

class TenantContext
{
    public bool $isActive = false;
    public bool $isGlobal = false; // True for Superadmins
    
    public bool $isCompanyAdmin = false; // NEW: Flag for Company Admin operations
    
    public ?array $allowedLocationIds = null; // Pre-computed hierarchy bounds for viewing users/offices
    public ?array $allowedCompanyIds = null;  // Pre-computed bounds for viewing/selecting Companies
    
    public ?int $membershipId = null;
    public ?int $companyId = null;
    public ?int $locationId = null; // Active operational working context
    public bool $isHomeOffice = false;

    // Cache for the active request's EffectivePermissionSet
    public ?EffectivePermissionSet $effectivePermissions = null;
    public ?EffectivePermissionSet $companyAdminPermissions = null; // Cache for layered capabilities

    public array $configs = [];

    /**
     * Safely retrieves the cached configuration for a specific reference type.
     */
    public function getConfig(string $referenceType): ?object
    {
        return $this->configs[$referenceType] ?? null;
    }

    /**
     * Helper to verify a permission across both standard operational and company admin sets.
     */
    public function hasPermission(string $perm): bool
    {
        if ($this->effectivePermissions && $this->effectivePermissions->has($perm)) {
            return true;
        }
        if ($this->companyAdminPermissions && $this->companyAdminPermissions->has($perm)) {
            return true;
        }
        return false;
    }
}