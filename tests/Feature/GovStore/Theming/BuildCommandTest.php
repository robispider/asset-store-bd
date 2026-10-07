<?php

namespace Tests\Feature\GovStore\Theming;

class BuildCommandTest extends ThemingTestCase
{
    public function test_build_writes_hashed_bundles_and_manifest(): void
    {
        $out = $this->tempDir();
        $this->artisan('gs-theme:build', ['--output' => $out])->assertSuccessful();
        $manifest = json_decode(file_get_contents($out.'/manifest.json'), true);

        $this->assertMatchesRegularExpression('/^kit\.[0-9a-f]{10}\.css$/', $manifest['kit']);
        $this->assertMatchesRegularExpression('/^packages\.[0-9a-f]{10}\.css$/', $manifest['packages']);
        $this->assertMatchesRegularExpression('/^gs-theme\.[0-9a-f]{10}\.js$/', $manifest['js']);
        $this->assertSame(['default', 'digital-blue', 'executive-neutral', 'institutional-green'], array_keys($manifest['themes']));
        foreach ($manifest['themes'] as $key => $file) {
            $css = file_get_contents($out.'/'.$file);
            $this->assertStringContainsString('[data-skin="'.$key.'"]{color-scheme:light;', $css);
            $this->assertStringContainsString('[data-skin="'.$key.'"][data-theme="dark"]{color-scheme:dark;', $css);
            $this->assertStringContainsString('@media print{', $css);
            $this->assertSame(substr(sha1($css), 0, 10), explode('.', basename($file))[1]);
        }
        $this->assertFileExists($out.'/'.$manifest['previews']['digital-blue']['dark']);

        // Rebuilding unchanged sources is stable; stale bundles are removed after a change.
        $this->artisan('gs-theme:build', ['--output' => $out])->assertSuccessful();
        $this->assertSame($manifest['kit'], json_decode(file_get_contents($out.'/manifest.json'), true)['kit']);
    }

    public function test_build_fails_for_an_invalid_published_theme_and_skips_invalid_drafts(): void
    {
        $dir = $this->copyShippedThemes();
        $this->writeTheme($dir, 'broken-draft', ['status' => 'draft'], ['color' => ['$type' => 'color', 'text' => ['$value' => '{nope}']]]);
        $this->useThemesPath($dir);
        $out = $this->tempDir();
        $this->artisan('gs-theme:build', ['--output' => $out])->assertSuccessful();
        $this->assertArrayNotHasKey('broken-draft', json_decode(file_get_contents($out.'/manifest.json'), true)['themes']);

        $this->writeTheme($dir, 'unfinished', [], [], ['$extensions' => ['gs.scaffold' => true]]);
        app(\GovStore\Theming\Themes\ThemeRepository::class)->flush();
        $this->artisan('gs-theme:build', ['--output' => $this->tempDir()])->assertFailed();
    }

    public function test_validate_command_json_output(): void
    {
        $this->artisan('gs-theme:validate', ['--json' => true])->assertSuccessful();
        $this->artisan('gs-theme:validate', ['key' => 'nope'])->assertFailed();
    }

    public function test_packages_can_register_css_into_the_packages_layer(): void
    {
        $file = $this->tempDir().'/storeops.css';
        file_put_contents($file, '.storeops-hub { color: var(--gs-color-text); }');
        app('gs.theme')->assets()->css('storeops', $file);
        $css = app('gs.theme')->compiler()->packagesCss();
        $this->assertStringContainsString("/* storeops: storeops.css */\n@layer gs-packages{", $css);
        $this->assertStringContainsString('.storeops-hub', $css);
        $this->expectException(\InvalidArgumentException::class);
        app('gs.theme')->assets()->css('storeops', '/does/not/exist.css');
    }
}
