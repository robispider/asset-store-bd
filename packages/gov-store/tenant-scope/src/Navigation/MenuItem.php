<?php

namespace GovStore\TenantScope\Navigation;

use Closure;

class MenuItem
{
    public string $id;

    /** Group id (config/navigation.php) or, for legacy definitions, another item's id. */
    public ?string $parent;

    /** Translation key, resolved at render time by label(). */
    public string $title;

    public string $icon;

    public ?string $route;

    /** @var string|array|null Only for route-less items; routed items take the route's `gov.can:` ability. */
    public string|array|null $permission;

    public int $order;

    public array $activePatterns;

    public array $children = [];

    public bool $strict = false;

    /** work | admin | user | context | actions | topbar | content | scripts | body-end; null inherits from the group. */
    public ?string $zone;

    public bool $isGroup = false;

    /** Blade view rendered instead of a link (user menu, top bar and page slots). */
    public ?string $view;

    public ?Closure $badge;

    public ?Closure $visible;

    /** POST fields: the entry renders as a one-button form to its route (e.g. "New goods receipt"). */
    public ?array $form;

    public function __construct(array $data)
    {
        $this->id = $data['id'];
        $this->parent = $data['group'] ?? $data['parent'] ?? null;
        $this->title = $data['label'] ?? $data['title'] ?? '';
        $this->icon = self::normalizeIcon($data['icon'] ?? 'fa fa-circle-o');
        $this->route = $data['route'] ?? null;
        $this->permission = $data['permission'] ?? null;
        $this->order = $data['order'] ?? 100;
        $this->activePatterns = $data['match'] ?? $data['active_patterns'] ?? [];
        $this->strict = $data['strict'] ?? false;
        $this->zone = $data['zone'] ?? null;
        $this->isGroup = $data['is_group'] ?? false;
        $this->view = $data['view'] ?? null;
        $this->badge = $data['badge'] ?? null;
        $this->visible = $data['visible'] ?? null;
        $this->form = $data['form'] ?? null;
    }

    /**
     * Theme tokens own colour: AdminLTE colour utilities are dropped and every icon is fixed-width.
     */
    public static function normalizeIcon(string $icon): string
    {
        $classes = array_filter(preg_split('/\s+/', trim($icon)), fn ($class) => ! preg_match('/^text-[a-z-]+$/', $class));

        return implode(' ', array_unique([...$classes, 'fa-fw']));
    }

    public function label(): string
    {
        return __($this->title);
    }

    /**
     * Lazily evaluated count for queue items; a failing counter never breaks the page.
     */
    public function badgeCount(): ?int
    {
        if ($this->isGroup) {
            $sum = array_sum(array_map(fn (MenuItem $child) => $child->badgeCount() ?? 0, $this->children));

            return $sum > 0 ? $sum : null;
        }
        if (! $this->badge) {
            return null;
        }
        if (! $this->badgeResolved) {
            $this->badgeResolved = true;
            try {
                $count = (int) ($this->badge)();
                $this->badgeValue = $count > 0 ? $count : null;
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $this->badgeValue;
    }

    private bool $badgeResolved = false;

    private ?int $badgeValue = null;

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
