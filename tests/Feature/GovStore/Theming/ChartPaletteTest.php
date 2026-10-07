<?php

namespace Tests\Feature\GovStore\Theming;

use App\Helpers\Helper;
use GovStore\Theming\ThemeManager;
use Illuminate\Http\Request;

class ChartPaletteTest extends ThemingTestCase
{
    public function test_foot_exports_exactly_the_core_fallback_palette(): void
    {
        $expected = [];
        for ($i = 0; $i < config('gs-theme.chart_fallback_count'); $i++) {
            $expected[] = strtoupper(Helper::defaultChartColors($i));
        }
        $this->assertSame(array_values(array_unique($expected)), app(ThemeManager::class)->chartFallbackPalette());
    }

    public function test_foot_config_flags_branding_routes_for_designed_themes_only(): void
    {
        $this->actor();
        $route = app('router')->getRoutes()->getByName('settings.branding.index');
        $this->assertNotNull($route);
        $request = Request::create('/'.$route->uri());
        $request->setRouteResolver(fn () => $route->bind($request));
        app()->instance('request', $request);

        $render = function () {
            app(ThemeManager::class)->forget();
            preg_match('#id="gs-theme-config">(.*?)</script>#s', view('gs-theme::foot')->render(), $m);

            return json_decode(html_entity_decode($m[1]), true);
        };
        $config = $render();
        $this->assertTrue($config['brandingNotice']);
        $this->assertStringContainsString(app('gs.theme')->theme()->label(), $config['i18n']['brandingNotice']);
        $this->assertContains(strtoupper(Helper::defaultChartColors(0)), $config['fallbackPalette']);

        config(['gs-theme.default' => 'default']);
        $this->assertFalse($render()['brandingNotice']);
    }

    public function test_js_plugin_never_hard_codes_colours(): void
    {
        $js = file_get_contents(base_path('packages/gov-store/theming/resources/js/gs-theme.js'));
        $this->assertStringContainsString("id: 'gsTheme'", $js);
        $this->assertStringContainsString('Chart.plugins.register', $js);
        $this->assertStringContainsString('recolorData', $js);
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{6}\b|rgba?\(\s*\d/', $js);
    }
}
