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

    // Inventory bounds are independent of office/user support jurisdictions.
    public ?array $allowedInventoryLocationIds = null;
    public ?array $allowedInventoryCompanyIds = null;
    
    public ?int $membershipId = null;
    public ?int $companyId = null;
    public ?int $locationId = null; // Active operational working context
    public bool $isHomeOffice = false;

    // Cache for the active request's EffectivePermissionSet
    public ?EffectivePermissionSet $effectivePermissions = null;
    public ?EffectivePermissionSet $companyAdminPermissions = null; // Cache for layered capabilities

    public array $configs = [];

    public function reset(): void
    {
        foreach (get_object_vars(new self) as $property => $value) {
            $this->$property = $value;
        }
    }

    public function canUseInventoryOffice(): bool
    {
        return ! $this->isGlobal && $this->locationId && $this->companyId
            && ($this->allowedInventoryLocationIds === null || in_array($this->locationId, $this->allowedInventoryLocationIds, true))
            && ($this->allowedInventoryCompanyIds === null || in_array($this->companyId, $this->allowedInventoryCompanyIds, true));
    }

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
