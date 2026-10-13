<?php

namespace Tests\Feature\GovStore\Navigation;

use GovStore\TenantScope\Compiler\NavigationHook;
use Illuminate\Support\Facades\Blade;

/**
 * Upstream contract: fails loudly when an upstream Snipe-IT merge moves something the
 * navigation hook attaches to. Fix only NavigationHook::ANCHORS — never edit the core layout.
 */
class SnipeItNavContractTest extends NavigationTestCase
{
    private function layout(): string
    {
        return file_get_contents(resource_path('views/layouts/default.blade.php'));
    }

    public function test_core_layout_carries_no_govstore_edits(): void
    {
        $this->assertDoesNotMatchRegularExpression('/gov(scope|store|mem)::|gov\.requests\.|GOV-STORE/', $this->layout(),
            'GovStore navigation attaches through NavigationHook; keep layouts/default.blade.php identical to upstream');
    }

    public static function anchors(): array
    {
        return array_map(fn ($zone) => [$zone], array_keys(NavigationHook::ANCHORS));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('anchors')]
    public function test_each_anchor_matches_upstream_exactly_once(string $zone): void
    {
        [$pattern] = NavigationHook::ANCHORS[$zone];
        $this->assertSame(1, preg_match_all($pattern, $this->layout()), "Anchor for '{$zone}' moved upstream; update NavigationHook::ANCHORS");
    }

    public function test_zones_land_in_the_intended_places(): void
    {
        $out = NavigationHook::apply($this->layout());
        $at = fn (string $zone) => strpos($out, "['zone' => '{$zone}']");
        foreach (['work', 'admin', 'user', 'topbar', 'content', 'scripts', 'body-end'] as $zone) {
            $this->assertSame(1, substr_count($out, "['zone' => '{$zone}']"), $zone);
        }
        $sidebar = strpos($out, '<ul class="sidebar-menu"');
        // Work zone: after the Dashboard, before Assets (decision D1).
        $this->assertGreaterThan(strpos($out, "route('home')", $sidebar), $at('work'));
        $this->assertLessThan(strpos($out, "trans('general.assets')", $sidebar), $at('work'));
        // Admin zone: after Snipe-IT's Settings and Reports, still inside the sidebar list (decision D4).
        $this->assertGreaterThan(strpos($out, "trans('general.reports')", $sidebar), $at('admin'));
        $this->assertLessThan(strpos($out, '<!-- /.sidebar -->'), $at('admin'));
        // User zone: inside the user menu, before Logout.
        $this->assertLessThan(strpos($out, "route('logout.get')"), $at('user'));
        $this->assertGreaterThan(strpos($out, "route('profile')"), $at('user'));
        $this->assertLessThan(strrpos($out, '</body>'), $at('body-end'));
    }

    public function test_compiles_alongside_the_theming_hook(): void
    {
        $path = resource_path('views/layouts/default.blade.php');
        Blade::setPath($path);
        $compiled = Blade::compileString($this->layout());
        $this->assertSame(7, substr_count($compiled, 'govscope::nav.zone'));
        $this->assertStringContainsString('/* '.NavigationHook::VERSION.' */', $compiled);
        if (class_exists(\GovStore\Theming\Compiler\LayoutHook::class)) {
            $this->assertStringStartsWith('<?php /* '.\GovStore\Theming\Compiler\LayoutHook::VERSION.' */ ?>', $compiled);
        }
    }

    public function test_hook_ignores_other_views_is_idempotent_and_degrades_gracefully(): void
    {
        Blade::setPath(resource_path('views/dashboard.blade.php'));
        $this->assertSame('<ul class="sidebar-menu"></ul>', (new NavigationHook)('<ul class="sidebar-menu"></ul>'));

        $once = NavigationHook::apply($this->layout());
        Blade::setPath(resource_path('views/layouts/default.blade.php'));
        $this->assertSame($once, (new NavigationHook)($once));

        $fragment = NavigationHook::apply('<div>fragment</div>');
        $this->assertStringNotContainsString('govscope::nav.zone', $fragment);
    }
}
