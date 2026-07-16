<?php

namespace GovStore\TenantScope\Navigation;

use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\Organization\Models\LocationProfile;
use Exception;

class MenuRegistry
{
    protected array $items = [];

    /**
     * Register a new menu definition.
     * Throws on duplicate ID to prevent silent collisions between packages.
     */
    public function register(array $definition): void
    {
        if (isset($this->items[$definition['id']])) {
            throw new Exception("Duplicate GovStore Menu Registration Key: {$definition['id']}");
        }

        $this->items[$definition['id']] = new MenuItem($definition);
    }

    /**
     * Compiles the sorted, permission-filtered hierarchical tree.
     *
     * Authorization model (evaluated in order, OR logic across array items):
     *  1. Superuser / admin     → bypass, always visible
     *  2. 'storekeeper'         → role row in gov_office_responsibilities for active location
     *  3. 'approver'            → primary_approver or final_approver row for active location
     *  4. 'office_admin'        → office_admin_id on the LocationProfile for active location
     *  5. any other string      → checked via effectivePermissions capability set
     *
     * The 'permission' key accepts either a single string or an array of qualifiers.
     * Access is granted if the user satisfies ANY ONE of the listed qualifiers.
     */
    /**
     * Compiles the sorted, permission-filtered hierarchical tree.
     */
    public function tree(): array
    {
        $context  = app(TenantContext::class);
        $flatList = [];

        foreach ($this->items as $item) {
            if ($item->permission) {
                $user = auth()->user();

                if (!$user) {
                    continue;
                }

                // 1. Global Superuser Bypass — always allowed
                if ($user->isSuperUser()) {
                    // Allowed — fall through to $flatList
                } else {
                    $qualifiers = is_array($item->permission) ? $item->permission : [$item->permission];
                    $locationId = $context->locationId;
                    $hasAccess  = false;

                    foreach ($qualifiers as $perm) {
                        // 2. Explicit Admin/Superuser Verification
                        if ($perm === 'admin') {
                            $hasAccess = $user->isSuperUser() || $user->hasAccess('admin');
                        }
                        // 3. Office Admin Verification
                        elseif ($perm === 'office_admin') {
                            $hasAccess = LocationProfile::where('location_id', $locationId)
                                ->where('office_admin_id', $user->id)
                                ->exists();
                        } 
                        // 4. ICT Officer Verification
                        elseif ($perm === 'ict_officer') {
                            $hasAccess = \GovStore\Organization\Models\IctJurisdiction::where('user_id', $user->id)
                                ->exists();
                        }
                        // 5. Contextual Role-Slug Verification
                        elseif (in_array($perm, ['storekeeper', 'approver'])) {
                            $roleSlugs = ($perm === 'approver')
                                ? ['primary_approver', 'final_approver']
                                : ['storekeeper'];

                            $hasAccess = OfficeResponsibility::where('user_id', $user->id)
                                ->where('location_id', $locationId)
                                ->whereIn('role_slug', $roleSlugs)
                                ->exists();
                        } 
                        // 6. Standard Capability/Permission Verification
                        else {
                            $hasAccess = $context->effectivePermissions
                                && $context->effectivePermissions->has($perm);
                        }

                        if ($hasAccess) {
                            break; // Short-circuit
                        }
                    }

                    if (!$hasAccess) {
                        continue;
                    }
                }
            }

            $flatList[$item->id] = $item;
        }

        $tree = [];

        foreach ($flatList as $item) {
            if ($item->parent && isset($flatList[$item->parent])) {
                $flatList[$item->parent]->children[] = $item;
            } elseif (!$item->parent) {
                $tree[] = $item;
            }
        }

        return $this->sortMenuTree($tree);
    }

    protected function sortMenuTree(array $tree): array
    {
        usort($tree, function ($a, $b) {
            return $a->order <=> $b->order;
        });

        foreach ($tree as $item) {
            if (!empty($item->children)) {
                $item->children = $this->sortMenuTree($item->children);
            }
        }

        return $tree;
    }
}
