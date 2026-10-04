<?php

namespace GovStore\TenantScope\Navigation;

use Exception;
use GovStore\TenantScope\Services\GovAccess;

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
     */
    public function tree(): array
    {
        $access = app(GovAccess::class);
        $flatList = [];
        foreach ($this->items as $definition) {
            $item = clone $definition;
            $item->children = [];
            $permission = $item->permission;
            if ($item->route && ($route = app('router')->getRoutes()->getByName($item->route))) {
                foreach ($route->gatherMiddleware() as $middleware) {
                    if (str_starts_with($middleware, 'gov.can:')) {
                        $permission = substr($middleware, 8);
                        break;
                    }
                }
            }
            if ($permission && ! $access->qualifier(auth()->user(), $permission, $item->strict)) {
                continue;
            }
            $flatList[$item->id] = $item;
        }

        $tree = [];

        foreach ($flatList as $item) {
            if ($item->parent && isset($flatList[$item->parent])) {
                $flatList[$item->parent]->children[] = $item;
            } elseif (! $item->parent) {
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
            if (! empty($item->children)) {
                $item->children = $this->sortMenuTree($item->children);
            }
        }

        return $tree;
    }
}
