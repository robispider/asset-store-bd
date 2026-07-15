<?php

namespace GovStore\TenantScope\Navigation;

use Illuminate\Support\Facades\Route;

class MenuItem
{
    public string $id;
    public ?string $parent;
    public string $title;
    public string $icon;
    public ?string $route;
    /** @var string|array|null Single qualifier string or array of qualifier strings */
    public string|array|null $permission;
    public int $order;
    public array $activePatterns;
    public array $children = [];

    public function __construct(array $data)
    {
        $this->id             = $data['id'];
        $this->parent         = $data['parent'] ?? null;
        $this->title          = $data['title'];
        $this->icon           = $data['icon'] ?? 'fa fa-circle-o';
        $this->route          = $data['route'] ?? null;
        $this->permission     = $data['permission'] ?? null;
        $this->order          = $data['order'] ?? 100;
        $this->activePatterns = $data['active_patterns'] ?? [];
    }

    /**
     * Determine if this menu item or any of its children matches the active request route.
     */
    public function isActive(): bool
    {
        if ($this->route && request()->routeIs($this->route)) {
            return true;
        }

        foreach ($this->activePatterns as $pattern) {
            if (request()->routeIs($pattern) || request()->is($pattern)) {
                return true;
            }
        }

        foreach ($this->children as $child) {
            if ($child->isActive()) {
                return true;
            }
        }

        return false;
    }
}
