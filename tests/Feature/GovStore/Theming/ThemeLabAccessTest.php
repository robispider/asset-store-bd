<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\TenantScope\Services\EffectivePermissionSet;
use GovStore\Theming\Http\Controllers\ThemeLabController;
use Illuminate\Http\Request;
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

    public function test_kill_switch_returns_404_for_everyone(): void
    {
        config(['gs-theme.lab_enabled' => false]);
        $this->actor('superuser');
        try {
            $this->lab('index');
            $this->fail('Expected 404');
        } catch (HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_denied_without_ability_using_tenant_scope_payload(): void
    {
        $this->actor('company_admin');
        $response = $this->lab('index');
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('theming.lab.view', $response->getData(true)['ability']);
        $this->assertArrayHasKey('reference_id', $response->getData(true));
    }

    public function test_superuser_role_gets_matrix_with_all_themes_and_validation(): void
    {
        $this->actor('superuser');
        $view = $this->lab('index');
        $this->assertSame('gs-theme::lab.matrix', $view->name());
        $this->assertCount(5, $view->getData()['themes']);
        $this->assertArrayHasKey('institutional-green', $view->getData()['validation']);

        $filtered = $this->lab('index', ['themes' => ['digital-blue']]);
        $this->assertSame(['digital-blue'], array_keys($filtered->getData()['themes']));
    }

    public function test_permission_strings_never_open_the_lab(): void
    {
        $this->actor('employee', 9);
        app(\GovStore\TenantScope\Contexts\TenantContext::class)->effectivePermissions = new EffectivePermissionSet(['theming.lab.view']);
        $this->assertSame(403, $this->lab('index')->getStatusCode());
    }

    public function test_focus_redirects_to_preview_and_applies_variant_overrides(): void
    {
        $this->actor('superuser');
        $redirect = $this->lab('focus', ['mode' => 'dark'], 'digital-blue');
        $this->assertSame(302, $redirect->getStatusCode());
        $this->assertStringContainsString('gs_preview=digital-blue', $redirect->getTargetUrl());
        $this->assertStringContainsString('gs_mode=dark', $redirect->getTargetUrl());

        $view = $this->lab('focus', ['gs_preview' => 'digital-blue', 'gs_mode' => 'dark', 'gs_variant' => ['table' => 'ledger', 'density' => 'bogus']], 'digital-blue');
        $this->assertSame('gs-theme::lab.focus', $view->name());
        $this->assertSame(['table' => 'ledger'], $view->getData()['overrides']);
        $this->assertSame('ledger', $view->getData()['variants']['table']);

        $this->assertStringContainsString('data-gs-table="ledger"', app('gs.theme')->htmlAttributes());
        $this->assertStringContainsString('data-skin="digital-blue"', app('gs.theme')->htmlAttributes());
    }

    public function test_unknown_theme_is_404(): void
    {
        $this->actor('superuser');
        $this->expectException(HttpException::class);
        $this->lab('focus', [], 'nope');
    }
}
