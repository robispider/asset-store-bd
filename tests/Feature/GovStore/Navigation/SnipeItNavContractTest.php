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
        foreach (['work', 'admin', 'user', 'context', 'actions', 'topbar', 'content', 'scripts', 'body-end'] as $zone) {
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
        // Navbar: the office chip sits after the logo, ＋ New where "Create New" was, and the
        // topbar zone (inbox, basket) before the user menu.
        $navbarRight = strpos($out, '<!-- Navbar Right Menu -->');
        $this->assertGreaterThan(strpos($out, 'left-navblock'), $at('context'));
        $this->assertLessThan($navbarRight, $at('context'));
        $this->assertGreaterThan(strpos($out, 'id="tagSearch"'), $at('actions'));
        $this->assertLessThan($at('topbar'), $at('actions'));
        $this->assertLessThan(strpos($out, '<!-- User Account:'), $at('topbar'));
    }

    public static function rewrites(): array
    {
        return array_map(fn ($name) => [$name], array_keys(NavigationHook::REWRITES));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rewrites')]
    public function test_each_rewritten_core_block_matches_upstream_exactly_once(string $name): void
    {
        [$pattern] = NavigationHook::REWRITES[$name];
        $this->assertSame(1, preg_match_all($pattern, $this->layout()), "Core block '{$name}' moved upstream; update NavigationHook::REWRITES");
    }

    public function test_rewrites_switch_core_blocks_at_render_time_without_deleting_them(): void
    {
        $layout = $this->layout();
        $out = NavigationHook::apply($layout);
        foreach (NavigationHook::REWRITES as [$pattern, $mode, $flag]) {
            preg_match($pattern, $layout, $match);
            // The stock markup survives verbatim behind its config flag, so turning the flag off
            // restores upstream Snipe-IT without recompiling the view.
            $this->assertStringContainsString($match[0], $out);
            $guard = $mode === 'replace' ? "@if(config('{$flag}', true))" : "@unless(config('{$flag}', true))";
            $this->assertStringContainsString($guard, $out);
            $this->assertLessThan(strpos($out, $match[0]), strpos($out, $guard));
        }
        // "Create New" (Asset, License, …) is no longer the default branch.
        $this->assertMatchesRegularExpression("/@include\('govscope::nav\.zone', \['zone' => 'actions'\]\)\s*@else\s*@can\('admin'\)/", $out);
    }

    public function test_a_moved_core_block_is_left_as_stock_markup(): void
    {
        $moved = str_replace("trans('general.create')", "trans('general.create_new')", $this->layout());
        $out = NavigationHook::apply($moved);
        $this->assertStringNotContainsString("['zone' => 'actions']", $out);
        $this->assertStringContainsString("trans('general.create_new')", $out);
    }

    public function test_compiles_alongside_the_theming_hook(): void
    {
        $path = resource_path('views/layouts/default.blade.php');
        Blade::setPath($path);
        $compiled = Blade::compileString($this->layout());
        $this->assertSame(9, substr_count($compiled, 'govscope::nav.zone'));
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
