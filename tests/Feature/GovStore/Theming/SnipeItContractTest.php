<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Compiler\LayoutHook;
use Illuminate\Support\Facades\Blade;

/**
 * Upstream contract: fails loudly when an upstream Snipe-IT merge removes something the
 * theming hook or adapter depends on. Fix only LayoutHook anchors or the adapter files.
 */
class SnipeItContractTest extends ThemingTestCase
{
    private function compile(string $layout): string
    {
        $path = resource_path('views/layouts/'.$layout.'.blade.php');
        Blade::setPath($path);

        return Blade::compileString(file_get_contents($path));
    }

    public static function layouts(): array
    {
        return [['default'], ['basic'], ['setup']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('layouts')]
    public function test_hook_attaches_to_each_target_layout(string $layout): void
    {
        $compiled = $this->compile($layout);
        $this->assertStringStartsWith('<?php /* '.LayoutHook::VERSION.' */ ?>', $compiled);
        $this->assertDoesNotMatchRegularExpression('/<html\b[^>]*data-theme="light"[^>]*htmlAttributes/', $compiled, 'core hard-coded data-theme not replaced');
        // The attributes land at the end of the tag, leaving core's Blade echoes (which contain `->`) intact.
        preg_match('/<html\b.*?htmlAttributes\(\); \?>>/s', $compiled, $tag);
        $this->assertNotEmpty($tag, 'theme attributes must close the <html> tag');
        if ($layout !== 'setup') {
            $this->assertStringContainsString("app()->getLocale()", $tag[0]);
        }
        $this->assertSame(1, preg_match_all('/<\?php/', preg_replace('/<\?php echo e\(.*?\); \?>/s', '', $tag[0])), 'unexpected PHP inside <html>: '.$tag[0]);
        $this->assertStringContainsString("'gs-theme::head'", $compiled);
        $this->assertStringContainsString("'gs-theme::foot'", $compiled);
        $this->assertLessThan(strrpos($compiled, '</body>'), strrpos($compiled, "'gs-theme::foot'"));
        $this->assertLessThan(strpos($compiled, '</head>'), strpos($compiled, "'gs-theme::head'"));
    }

    public static function customCssLayouts(): array
    {
        return [['default'], ['basic']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('customCssLayouts')]
    public function test_theme_head_loads_after_core_styles_and_before_custom_css(string $layout): void
    {
        $source = file_get_contents(resource_path('views/layouts/'.$layout.'.blade.php'));
        $this->assertMatchesRegularExpression(LayoutHook::CUSTOM_CSS_ANCHOR, $source, 'Custom CSS anchor moved upstream — update LayoutHook::CUSTOM_CSS_ANCHOR');
        $compiled = $this->compile($layout);
        $head = strpos($compiled, "'gs-theme::head'");
        $this->assertLessThan(strpos($compiled, 'show_custom_css()'), $head);
        $lastCoreStyle = strrpos(substr($compiled, 0, $head), '</style>');
        if ($layout === 'default') {
            $this->assertNotFalse($lastCoreStyle, 'theme head must follow the core inline <style>');
        }
    }

    public function test_hook_ignores_other_views_and_is_idempotent(): void
    {
        Blade::setPath(resource_path('views/dashboard.blade.php'));
        $this->assertStringNotContainsString(LayoutHook::VERSION, (new LayoutHook)('<html><head></head><body></body></html>'));
        $once = LayoutHook::apply('<html data-theme="light"><head></head><body></body></html>');
        Blade::setPath(resource_path('views/layouts/default.blade.php'));
        $this->assertSame($once, (new LayoutHook)($once));
    }

    public function test_missing_anchors_degrade_gracefully(): void
    {
        $result = LayoutHook::apply('<div>fragment</div>');
        $this->assertStringNotContainsString('gs-theme::head', $result);
        $this->assertStringNotContainsString('gs-theme::foot', $result);
        $this->assertStringNotContainsString('htmlAttributes', $result);
    }

    public function test_every_mapped_snipeit_variable_still_exists_in_the_core_layout(): void
    {
        $core = file_get_contents(resource_path('views/layouts/default.blade.php'));
        preg_match_all('/^\s*(--[a-z0-9-]+)\s*:/m', file_get_contents(base_path('packages/gov-store/theming/adapter/snipeit-vars.css')), $m);
        $mapped = array_values(array_unique(array_filter($m[1], fn ($v) => ! str_starts_with($v, '--gs-'))));
        $this->assertGreaterThan(30, count($mapped));
        foreach ($mapped as $variable) {
            $this->assertMatchesRegularExpression('/'.preg_quote($variable, '/').'\s*:/', $core, "Snipe-IT no longer defines {$variable}; update adapter/snipeit-vars.css");
        }
    }

    public function test_adminlte_classes_used_by_the_adapter_still_exist_upstream(): void
    {
        $haystack = file_get_contents(resource_path('views/layouts/default.blade.php'))
            .file_get_contents(resource_path('views/layouts/basic.blade.php'))
            .(is_file(public_path('css/dist/all.css')) ? file_get_contents(public_path('css/dist/all.css')) : '');
        foreach (['main-header', 'main-sidebar', 'sidebar-menu', 'treeview-menu', 'content-wrapper', 'main-footer', 'navbar-custom-menu',
            'user-menu', 'login-page', 'box-header', 'nav-tabs-custom', 'info-box', 'small-box', 'callout', 'select2-results__option', 'datepicker',
            'fixed-table-toolbar', 'sidebar-collapse', 'sidebar-toggle', 'data-theme-toggle'] as $class) {
            $this->assertStringContainsString($class, $haystack, "Upstream no longer uses .{$class}; update adapter/snipeit.css");
        }
    }

    public function test_classic_keeps_branding_colours_and_designed_themes_map_them(): void
    {
        $vars = file_get_contents(base_path('packages/gov-store/theming/adapter/snipeit-vars.css'));
        [$classic, $designed] = explode('[data-skin]:not([data-skin="default"])', $vars, 2);
        // Classic never overrides Snipe-IT's Branding variables; kit tokens follow them instead.
        $this->assertDoesNotMatchRegularExpression('/^\s*--main-theme-color\s*:/m', $classic);
        $this->assertStringContainsString('--gs-color-primary: var(--main-theme-color)', $classic);
        $this->assertStringContainsString('--gs-color-link: var(--link-color)', $classic);
        // Designed themes provide their own colours.
        $this->assertStringContainsString('--main-theme-color: var(--gs-color-primary)', $designed);
        $this->assertStringContainsString('--link-color: var(--gs-color-link)', $designed);
        foreach (['snipeit.css', 'variants.css'] as $file) {
            $this->assertStringContainsString('[data-skin]:not([data-skin="default"])', file_get_contents(base_path('packages/gov-store/theming/adapter/'.$file)));
        }
    }

    public function test_adapter_overrides_are_layered_important_and_literal_free(): void
    {
        $adapter = file_get_contents(base_path('packages/gov-store/theming/adapter/snipeit.css'));
        $this->assertStringContainsString('@layer gs-adapter', $adapter);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b|rgba?\(\d/', preg_replace('#/\*.*?\*/#s', '', $adapter));
        $kit = app('gs.theme')->compiler()->kitCss();
        $this->assertStringStartsWith("/* gs-theme kit", $kit);
        $this->assertStringContainsString('@layer gs-adapter, gs-components, gs-packages, gs-theme;', $kit);
    }
}
