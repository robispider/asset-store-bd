<?php

namespace Tests\Feature\GovStore\Theming;

use App\Models\Setting;
use GovStore\Theming\Models\ThemePreference;
use GovStore\Theming\ThemeManager;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;

/**
 * Renders Snipe-IT's real login layout (layouts/basic) through the compile-time hook.
 * Full default-layout pages need the complete Snipe-IT schema (MySQL); those are covered by
 * the Playwright visual suite (scripts/live-tests/theme-visual).
 */
class LayoutRenderingTest extends ThemingTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('view:clear');
        // show_custom_css() normally re-reads settings from the database.
        $settings = (new class extends Setting
        {
            public function show_custom_css(): string
            {
                return (string) $this->custom_css;
            }
        })->forceFill(['site_name' => 'National Asset Register', 'custom_css' => '.admin-custom { margin: 0; }', 'header_color' => '#123456']);
        // Snipe-IT's view composer injects Setting::getSettings(); seed its static cache.
        (new \ReflectionProperty(Setting::class, '_cache'))->setValue(null, $settings);
        View::share('snipeSettings', $settings);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(Setting::class, '_cache'))->setValue(null, null);
        parent::tearDown();
    }

    private function renderLogin(): string
    {
        app(ThemeManager::class)->forget();

        return Blade::render("@extends('layouts.basic')\n@section('content')<div class=\"login-box-body\">Login</div>@endsection");
    }

    public static function themes(): array
    {
        return [['default'], ['institutional-green'], ['digital-blue'], ['executive-neutral'], ['lavender']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('themes')]
    public function test_login_layout_renders_each_theme_with_its_css(string $key): void
    {
        $user = $this->actor();
        ThemePreference::create(['user_id' => $user->id, 'theme' => $key, 'mode' => 'dark']);
        $html = $this->renderLogin();

        $this->assertMatchesRegularExpression('/<html[^>]*data-skin="'.$key.'"[^>]*>/', $html);
        $this->assertMatchesRegularExpression('/<html[^>]*data-theme="dark"[^>]*>/', $html);
        preg_match('/<html[^>]*>/', $html, $tag);
        $this->assertSame(1, substr_count($tag[0], 'data-theme='), 'core data-theme must be replaced, not duplicated');
        $this->assertStringContainsString('gs-theme-dev/theme-'.$key.'.css', $html);
        $this->assertStringContainsString('gs-theme-dev/kit.css', $html);
        $this->assertStringContainsString('gs-theme-dev/gs-theme.js', $html);
        $this->assertStringContainsString('id="gs-theme-config"', $html);
        // Theme CSS before the admin's Custom CSS, which stays the final word.
        $this->assertLessThan(strpos($html, '.admin-custom'), strpos($html, 'data-gs-theme-link'));
    }

    public function test_guests_get_organisation_default_and_local_mode(): void
    {
        auth()->logout();
        $html = $this->renderLogin();
        $this->assertStringContainsString('data-skin="institutional-green"', $html);
        $this->assertStringContainsString('data-gs-mode="system"', $html);
        $this->assertStringContainsString("localStorage.getItem('theme')", $html);
    }

    public function test_dev_assets_compile_on_request(): void
    {
        // Snipe-IT redirects every request to /setup on an empty database.
        $this->withoutMiddleware(\App\Http\Middleware\CheckForSetup::class);
        foreach (['kit.css' => '@layer gs-adapter', 'packages.css' => '@layer gs-adapter, gs-components', 'gs-theme.js' => 'GS.theme',
            'theme-digital-blue.css' => '[data-skin="digital-blue"][data-theme="dark"]'] as $file => $needle) {
            $response = $this->get('/gs-theme-dev/'.$file);
            $response->assertOk();
            $this->assertStringContainsString($needle, $response->getContent());
        }
        $this->get('/gs-theme-dev/theme-nope.css')->assertNotFound();
        $this->get('/gs-theme-dev/previews/digital-blue.dark.png')->assertOk();

        config(['gs-theme.dev_assets' => false]);
        $this->get('/gs-theme-dev/kit.css')->assertNotFound();
    }

    public function test_production_without_manifest_falls_back_to_stock_styles(): void
    {
        config(['gs-theme.dev_assets' => false, 'gs-theme.build_path' => $this->tempDir()]);
        app()->forgetInstance(\GovStore\Theming\Themes\Manifest::class);
        app()->forgetInstance(ThemeManager::class);
        $html = $this->renderLogin();
        $this->assertStringNotContainsString('data-gs-theme-link', $html);
        $this->assertStringNotContainsString('gs-theme-config', $html);
        $this->assertStringContainsString('data-skin=', $html);
    }

    public function test_foot_exports_core_fallback_palette_and_branding_notice_flag(): void
    {
        $this->actor();
        $html = $this->renderLogin();
        preg_match('#<script type="application/json" id="gs-theme-config">(.*?)</script>#s', $html, $m);
        $config = json_decode(html_entity_decode($m[1]), true);
        $this->assertSame('institutional-green', $config['key']);
        $this->assertFalse($config['brandingNotice']); // login is not a Branding/profile route
        $this->assertNotEmpty($config['i18n']['brandingNotice']);
    }
}
