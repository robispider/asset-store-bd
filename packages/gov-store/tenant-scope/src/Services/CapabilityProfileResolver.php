<?php

namespace GovStore\TenantScope\Services;

class CapabilityProfileResolver
{
    /**
     * Resolves and compiles the permissions array into an EffectivePermissionSet.
     */
    public function resolveSchema(?string $roleSlug): EffectivePermissionSet
    {
        $config = config('govstore-permissions');

        // Fallback default permissions for any basic logged-in employee
        $profileSlug = 'employee';
        $permissions = $config['profiles']['employee'] ?? [];

        // If an active responsibility is held, merge its profile permissions
        if ($roleSlug && isset($config['responsibilities'][$roleSlug])) {
            $profileSlug = $config['responsibilities'][$roleSlug];
            $profilePermissions = $config['profiles'][$profileSlug] ?? [];
            
            $permissions = array_merge($permissions, $profilePermissions);
        }

        return new EffectivePermissionSet($permissions, $roleSlug, $profileSlug);
    }
}