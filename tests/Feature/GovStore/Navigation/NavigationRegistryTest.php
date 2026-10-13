<?php

namespace Tests\Feature\GovStore\Navigation;

use GovStore\TenantScope\Navigation\MenuItem;
use GovStore\TenantScope\Navigation\MenuRegistry;

/**
 * Guards the registrations every gov-store package makes: labels are translation keys
 * that exist in both languages, groups stay small, visibility has one source of truth.
 */
class NavigationRegistryTest extends NavigationTestCase
{
    private const LOCALES = ['en-US', 'bn-BD'];

    /** @return MenuItem[] */
    private function links(): array
    {
        return array_filter(app(MenuRegistry::class)->all(), fn (MenuItem $item) => ! $item->view);
    }

    public function test_every_label_is_a_key_translated_in_both_languages(): void
    {
        $labels = array_merge(
            array_map(fn (MenuItem $item) => $item->title, $this->links()),
            array_column(config('govstore-navigation.groups'), 'label'),
            ['tenantops::nav.zone_work', 'tenantops::nav.zone_assets', 'tenantops::nav.zone_admin'],
        );
        $this->assertGreaterThan(50, count($labels));
        foreach ($labels as $key) {
            // A resolved string here means the label was translated at boot, in whatever locale booted.
            $this->assertStringContainsString('::', $key, "Register a translation key, not a resolved string: {$key}");
            $translations = [];
            foreach (self::LOCALES as $locale) {
                $translations[$locale] = trans($key, [], $locale);
                $this->assertNotSame($key, $translations[$locale], "{$key} is missing in {$locale}");
            }
            $this->assertNotSame($translations['en-US'], $translations['bn-BD'], "{$key} is not translated into bn-BD");
            $this->assertMatchesRegularExpression('/\p{Bengali}/u', $translations['bn-BD'], $key);
        }
    }

    public function test_every_entry_targets_a_known_group_route_or_view(): void
    {
        $groups = config('govstore-navigation.groups');
        $zones = ['work', 'admin', 'user', 'context', 'actions', 'topbar', 'content', 'scripts', 'body-end'];
        $sizes = [];
        foreach (app(MenuRegistry::class)->all() as $item) {
            if ($item->parent) {
                $this->assertArrayHasKey($item->parent, $groups, "{$item->id}: packages register leaves into configured groups only");
                $sizes[$item->parent] = ($sizes[$item->parent] ?? 0) + 1;
            } else {
                $this->assertContains($item->zone, $zones, "{$item->id}: a group or a zone is required");
            }
            if ($item->view) {
                $this->assertTrue(view()->exists($item->view), "{$item->id}: missing view {$item->view}");
            } else {
                $this->assertNotNull(app('router')->getRoutes()->getByName($item->route), "{$item->id}: missing route {$item->route}");
            }
        }
        foreach ($sizes as $group => $size) {
            $this->assertLessThanOrEqual(config('govstore-navigation.max_group_size'), $size, "{$group} has {$size} items; split it");
        }
    }

    public function test_routed_entries_take_visibility_from_their_route_only(): void
    {
        $registry = app(MenuRegistry::class);
        foreach ($this->links() as $item) {
            $route = app('router')->getRoutes()->getByName($item->route);
            $ability = collect($route->gatherMiddleware())->first(fn ($m) => is_string($m) && str_starts_with($m, 'gov.can:'));
            if ($ability) {
                $this->assertNull($item->permission, "{$item->id}: its route's {$ability} decides visibility; drop the dead 'permission'");
                $this->assertSame(substr($ability, 8), $registry->permissionFor($item));
            }
        }
    }

    public function test_icons_are_fixed_width_and_leave_colour_to_the_theme(): void
    {
        foreach ($this->links() as $item) {
            $this->assertStringContainsString('fa-fw', $item->icon, $item->id);
            $this->assertDoesNotMatchRegularExpression('/(^|\s)text-[a-z]/', $item->icon, $item->id);
        }
    }

    public function test_employees_use_the_govstore_request_flow_not_snipeit_requestable_assets(): void
    {
        $this->assertNotContains('assets.view.requestable', config('govstore-permissions.profiles.employee'));
    }
}
