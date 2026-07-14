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

        // 1. Defensively strip any accidental database-level group grants in memory
        $user->setRelation('groups', collect([]));

        // 2. Prepare the active context permissions (1 = Granted)
        $currentPermissions = [];
        foreach ($rawPermissions as $permissionKey) {
            $currentPermissions[$permissionKey] = 1;
        }

        // 3. Overwrite the native Eloquent attribute
        if (is_array($user->getAttributes()['permissions'] ?? null)) {
            $user->permissions = $currentPermissions;
        } else {
            $user->permissions = json_encode($currentPermissions);
        }

        // =========================================================================
        // SENTINEL CACHE BUSTING:
        // Force the Sentinel authentication trait to drop its pre-compiled 
        // permission cache and rebuild it using our newly injected string.
        // =========================================================================
        
        // Clear Snipe-IT's custom cached array
        unset($user->cached_permissions);

        // Force Sentinel to re-evaluate the raw permission JSON
        if (method_exists($user, 'getPermissionsInstance')) {
            $permissionsInstance = $user->getPermissionsInstance();
            if (method_exists($permissionsInstance, 'setPermissions')) {
                $permissionsInstance->setPermissions($currentPermissions);
            }
        }
    }
}