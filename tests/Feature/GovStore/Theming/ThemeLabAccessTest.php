<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\TenantScope\Services\EffectivePermissionSet;
use GovStore\Theming\Build\Contract;
use GovStore\Theming\Http\Controllers\ThemeLabController;
use GovStore\Theming\Lab\LabHealth;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ThemeLabAccessTest extends ThemingTestCase
{
    private function lab(string $method, array $query = [], ...$args)
    {
        $request = Request::create('/gov/theme-lab', 'GET', $query);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => auth()->user());
        app()->instance('request', $request);

        return app(ThemeLabController::class)->$method($request, ...$args);
    }

    /** Render a Lab view inside a stub layout (the real one needs Snipe-IT settings). */
    private function render($view): string
    {
        $dir = sys_get_temp_dir().'/gs-theme-lab-layout';
        @mkdir($dir.'/layouts', 0777, true);
        file_put_contents($dir.'/layouts/default.blade.php', "@yield('content')\n@stack('js')");
        app('view')->getFinder()->prependLocation($dir);
        app('view')->getDispatcher()->forget('composing: layouts.default');
        view()->share('errors', new ViewErrorBag);

        return $view->render();
    }

    public function test_kill_switch_returns_404_for_everyone(): void
    {
        config(['gs-theme.lab_enabled' => false]);
        $this->actor('superuser');
        foreach (['index', 'compare', 'health'] as $method) {
            try {
                $this->lab($method);
                $this->fail('Expected 404 from '.$method);
            } catch (HttpException $e) {
                $this->assertSame(404, $e->getStatusCode());
            }
        }
    }

    public function test_denied_without_ability_using_tenant_scope_payload(): void
    {
        $this->actor('company_admin');
        foreach (['index', 'compare', 'health'] as $method) {
            $response = $this->lab($method);
            $this->assertSame(403, $response->getStatusCode(), $method);
            $this->assertSame('theming.lab.view', $response->getData(true)['ability']);
            $this->assertArrayHasKey('reference_id', $response->getData(true));
        }
    }

    public function test_permission_strings_never_open_the_lab(): void
    {
        $this->actor('employee', 9);
        app(\GovStore\TenantScope\Contexts\TenantContext::class)->effectivePermissions = new EffectivePermissionSet(['theming.lab.view']);
        $this->assertSame(403, $this->lab('index')->getStatusCode());
    }

    public function test_index_opens_the_workspace_on_the_viewers_theme(): void
    {
        $this->actor('superuser');
        $view = $this->lab('index');
        $this->assertSame('gs-theme::lab.workspace', $view->name());
        $this->assertSame(app('gs.theme')->key(), $view->getData()['theme']->key);
        $this->assertCount(6, $view->getData()['all']);
        $this->assertSame(['mode' => 'light', 'section' => 'overview', 'width' => 'desktop', 'tokens' => false], $view->getData()['state']);
    }

    public function test_workspace_keeps_the_page_theme_and_validates_state_and_overrides(): void
    {
        $this->actor('superuser');
        $view = $this->lab('focus', ['mode' => 'split', 'section' => 'colour', 'width' => 'bogus', 'tokens' => '1',
            'variant' => ['table' => 'ledger', 'density' => 'bogus', 'header' => 'dark', 'nope' => 'x']], 'digital-blue');

        $this->assertSame('gs-theme::lab.workspace', $view->name());
        $this->assertSame('digital-blue', $view->getData()['theme']->key);
        $this->assertSame(['mode' => 'split', 'section' => 'colour', 'width' => 'desktop', 'tokens' => true], $view->getData()['state']);
        // header=dark is digital-blue's own value, so it is not an override.
        $this->assertSame(['table' => 'ledger'], $view->getData()['overrides']);
        $this->assertSame('ledger', $view->getData()['variants']['table']);
        // No gs_preview: the Lab chrome stays in the viewer's own theme.
        $this->assertStringNotContainsString('data-skin="digital-blue"', app('gs.theme')->htmlAttributes());
    }

    public function test_workspace_renders_panels_tuning_and_colour_table(): void
    {
        $this->actor('superuser');
        $html = $this->render($this->lab('focus', ['variant' => ['table' => 'ledger']], 'lavender'));

        $this->assertStringContainsString('data-gs-lab="workspace"', $html);
        $this->assertStringNotContainsString('<x-gs::', $html);
        foreach (['light', 'dark'] as $mode) {
            $this->assertMatchesRegularExpression('/id="gs-panel-'.$mode.'" data-skin="lavender" data-theme="'.$mode.'"[^>]*data-gs-table="ledger"/', $html);
        }
        foreach (array_keys(ThemeLabController::TABS) as $tab) {
            $this->assertStringContainsString('data-value="'.$tab.'"', $html);
        }
        $this->assertStringContainsString('data-gs-lab-set-variant="status_style"', $html);
        $this->assertStringContainsString('&quot;table&quot;: &quot;ledger&quot;', $html);
        $this->assertStringContainsString(Contract::cssVar('color.text-muted'), $html);
        $this->assertStringContainsString('gs-theme:make my-theme --from=lavender', $html);
        $this->assertStringContainsString('gs_preview=lavender', $html);
    }

    public function test_compare_defaults_to_three_themes_and_filters_unknown_keys(): void
    {
        $this->actor('superuser');
        $view = $this->lab('compare');
        $this->assertSame('gs-theme::lab.compare', $view->name());
        $this->assertCount(3, $view->getData()['picked']);
        $this->assertSame('light', $view->getData()['mode']);

        $view = $this->lab('compare', ['themes' => ['lavender', 'nope', 'default'], 'mode' => 'both']);
        $this->assertSame(['lavender', 'default'], $view->getData()['picked']);
        $html = $this->render($view);
        $this->assertStringNotContainsString('<x-gs::', $html);
        $this->assertStringContainsString('data-gs-lab-card="lavender"', $html);
        $this->assertMatchesRegularExpression('/data-gs-lab-card="digital-blue"\s+hidden/', $html);
        $this->assertStringContainsString('data-gs-lab-glance-row', $html);
    }

    public function test_health_reports_every_contract_pair_for_every_theme_and_mode(): void
    {
        $this->actor('superuser');
        $view = $this->lab('health', ['mode' => 'dark', 'theme' => 'lavender']);
        $this->assertSame('gs-theme::lab.health', $view->name());
        $report = $view->getData()['report'];
        foreach (LabHealth::MODES as $mode) {
            $this->assertCount(count(Contract::CONTRAST_PAIRS), $report['modes'][$mode]['rows']);
            $this->assertSame(count(Contract::CONTRAST_PAIRS) * 6, $report['modes'][$mode]['total']);
        }
        $this->assertStringStartsWith('dark|lavender|', $view->getData()['selected']);
        foreach (array_unique(LabHealth::SLUGS) as $slug) {
            $this->assertNotSame('gs-theme::appearance.lab.checks.'.$slug.'.name', __('gs-theme::appearance.lab.checks.'.$slug.'.name'));
        }

        $html = $this->render($view);
        $this->assertStringNotContainsString('<x-gs::', $html);
        $this->assertStringContainsString('id="gs-lab-health-data"', $html);
        $this->assertStringContainsString('data-gs-lab-cell="dark|lavender|text-surface"', $html);
    }

    public function test_fix_suggests_the_nearest_passing_shade(): void
    {
        $this->actor('superuser');
        $theme = app('gs.theme')->repository()->find('default');
        $health = new LabHealth(app('gs.theme')->fonts());
        $fix = $health->fix($theme, 'light', ['fg' => 'color.text-muted', 'bg' => 'color.surface', 'min' => 4.5, 'fgValue' => '#9AA0A6', 'bgValue' => '#FFFFFF']);

        $this->assertSame('color.text-muted', $fix['token']);
        $this->assertGreaterThanOrEqual(4.5, $fix['ratio']);
        $this->assertLessThan(4.9, $fix['ratio']);
        $this->assertSame('themes/default/tokens.light.json', $fix['file']);
        $this->assertSame(['color' => ['text-muted' => ['$value' => $fix['value']]]], json_decode($fix['json'], true));

        // "on-*" pairs deepen the role colour, not the label.
        $fix = $health->fix($theme, 'light', ['fg' => 'color.on-warning', 'bg' => 'color.warning', 'min' => 4.5, 'fgValue' => '#FFFFFF', 'bgValue' => '#F0A020']);
        $this->assertSame('color.warning', $fix['token']);
    }

    public function test_unknown_theme_is_404(): void
    {
        $this->actor('superuser');
        $this->expectException(HttpException::class);
        $this->lab('focus', [], 'nope');
    }
}
