<?php

namespace GovStore\TenantScope\Services;

use App\Models\User;

class SnipePermissionAdapter
{
    /**
     * Translates an EffectivePermissionSet into native Snipe-IT permissions 
     * and applies them to the User model instance in memory.
     */
    public function adaptAndInject(User $user, EffectivePermissionSet $permissionSet): void
    {
        $rawPermissions = $permissionSet->getPermissions();

        // 1. Resolve current in-memory user permissions array safely
        $currentPermissions = [];
        if (!empty($user->permissions)) {
            $currentPermissions = is_string($user->permissions)
                ? (json_decode($user->permissions, true) ?: [])
                : (array) $user->permissions;
        }

        // 2. Grant the active context permissions in memory (1 = Granted)
        foreach ($rawPermissions as $permissionKey) {
            $currentPermissions[$permissionKey] = 1;
        }

        // 3. Overwrite the in-memory model attribute
        // IMPORTANT: $user->save() is NEVER called. This modification exists only in RAM for this request.
        if (is_array($user->getAttributes()['permissions'] ?? null)) {
            $user->permissions = $currentPermissions;
        } else {
            $user->permissions = json_encode($currentPermissions);
        }
    }
}