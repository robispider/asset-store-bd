<?php

namespace GovStore\TenantScope\Services;

class EffectivePermissionSet
{
    protected array $permissions = [];
    protected ?string $roleSlug = null;
    protected ?string $profileSlug = null;

    public function __construct(array $permissions, ?string $roleSlug = null, ?string $profileSlug = null)
    {
        $this->permissions = array_unique($permissions);
        $this->roleSlug = $roleSlug;
        $this->profileSlug = $profileSlug;
    }

    /**
     * Checks if a specific business permission is granted in this set.
     */
    public function has(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }

    /**
     * Get the raw array of pre-computed permissions.
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * Get the active responsibility role slug (e.g. 'storekeeper').
     */
    public function getRole(): ?string
    {
        return $this->roleSlug;
    }

    /**
     * Get the active capability profile slug (e.g. 'inventory_operator').
     */
    public function getProfile(): ?string
    {
        return $this->profileSlug;
    }
}