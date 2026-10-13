<?php

namespace GovStore\TenantScope\Navigation;

use Exception;
use GovStore\TenantScope\Services\GovAccess;
use InvalidArgumentException;
use WeakMap;

/**
 * Central navigation registry. Packages register leaves into the groups declared once in
 * config/navigation.php (`govstore-navigation`); the groups, not the packages, define the
 * information architecture. Each zone is attached to Snipe-IT's layout by NavigationHook.
 */
class MenuRegistry
{
    public const SIDEBAR_ZONES = ['work', 'admin'];

    protected array $items = [];

    /** Per-request snapshot shared by the zones of one page render. */
    private WeakMap $snapshots;

    public function __construct()
    {
        $this->snapshots = new WeakMap;
    }

    /**
     * Register a new menu definition.
     * Throws on duplicate ID to prevent silent collisions between packages.
     */
    public function register(array $definition): void
    {
        if (isset($this->items[$definition['id']])) {
            throw new Exception("Duplicate GovStore Menu Registration Key: {$definition['id']}");
        }
        if (isset($definition['group']) && ! array_key_exists($definition['group'], config('govstore-navigation.groups', []))) {
            throw new InvalidArgumentException("Unknown GovStore menu group '{$definition['group']}' for '{$definition['id']}'.");
        }

        $this->items[$definition['id']] = new MenuItem($definition);
    }

    /** @return MenuItem[] Raw registrations, for diagnostics and tests. */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * One zone of the current page. All zones of a render share one permission snapshot.
     */
    public function zone(string $zone): array
    {
        $request = request();
        $userId = auth()->id();
        if (! isset($this->snapshots[$request]) || $this->snapshots[$request]['user'] !== $userId) {
            $this->snapshots[$request] = ['user' => $userId, 'tree' => $this->tree()];
        }

        return array_values(array_filter($this->snapshots[$request]['tree'], fn (MenuItem $item) => $item->zone === $zone));
    }

    /**
     * Visible leaves of one configured group on the current page (after pruning and collapse),
     * from the shared snapshot — e.g. the navbar inbox reads 'gov-queues'.
     *
     * @return MenuItem[]
     */
    public function groupItems(string $group): array
    {
        foreach ($this->zone(config("govstore-navigation.groups.{$group}.zone", 'work')) as $item) {
            if ($item->id === $group) {
                return $item->children;
            }
            if ($item->parent === $group) {
                return [$item];
            }
        }

        return [];
    }

    /**
     * Compiles the sorted, permission-filtered hierarchical tree.
     * Configured groups without a visible child are pruned; a group with one visible child
     * renders as that child.
     */
    public function tree(?string $zone = null): array
    {
        $access = app(GovAccess::class);
        // One live snapshot per render, discarded afterwards. No session cache
        // may delay assignment changes, revocation or temporary-cover expiry.
        $roles = $access->roles(auth()->user());
        $flatList = [];
        foreach ($this->items as $definition) {
            $item = clone $definition;
            $item->children = [];
            if ($item->visible && ! ($item->visible)()) {
                continue;
            }
            $permission = $this->permissionFor($item);
            if ($permission && ! $access->qualifier(auth()->user(), $permission, $item->strict, $roles)) {
                continue;
            }
            $flatList[$item->id] = $item;
        }

        $groups = $this->groups();
        $tree = [];

        foreach ($flatList as $item) {
            if ($item->parent && isset($groups[$item->parent])) {
                $groups[$item->parent]->children[] = $item;
            } elseif ($item->parent && isset($flatList[$item->parent])) {
                $flatList[$item->parent]->children[] = $item;
            } elseif (! $item->parent) {
                $item->zone ??= 'work';
                $tree[] = $item;
            }
        }

        foreach ($groups as $group) {
            if (count($group->children) === 1 && ! $group->children[0]->children) {
                $only = $group->children[0];
                $only->order = $group->order;
                $only->zone = $group->zone;
                $tree[] = $only;
            } elseif ($group->children) {
                $tree[] = $group;
            }
        }

        if ($zone !== null) {
            $tree = array_filter($tree, fn (MenuItem $item) => $item->zone === $zone);
        }

        return $this->sortMenuTree(array_values($tree));
    }

    /**
     * A routed item is visible exactly when its route's `gov.can:` ability allows it (menu = gate);
     * the declared permission applies only to route-less items and routes without that middleware.
     */
    public function permissionFor(MenuItem $item): string|array|null
    {
        if ($item->route && ($route = app('router')->getRoutes()->getByName($item->route))) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && str_starts_with($middleware, 'gov.can:')) {
                    return substr($middleware, 8);
                }
            }
        }

        return $item->permission;
    }

    /** @return MenuItem[] */
    protected function groups(): array
    {
        $groups = [];
        foreach (config('govstore-navigation.groups', []) as $id => $group) {
            $groups[$id] = new MenuItem([
                'id' => $id,
                'label' => $group['label'],
                'icon' => $group['icon'] ?? 'fas fa-folder',
                'order' => $group['order'] ?? 100,
                'zone' => $group['zone'] ?? 'work',
                'is_group' => true,
            ]);
        }

        return $groups;
    }

    protected function sortMenuTree(array $tree): array
    {
        usort($tree, fn (MenuItem $a, MenuItem $b) => [$a->order, $a->id] <=> [$b->order, $b->id]);

        foreach ($tree as $item) {
            if (! empty($item->children)) {
                $item->children = $this->sortMenuTree($item->children);
            }
        }

        return $tree;
    }
}
