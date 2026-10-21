<?php

namespace Tests\Feature\GovStore\Theming;

use GovStore\Theming\Http\Controllers\ThemeLabController;
use GovStore\Theming\Lab\GeoLabFixture;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;

/** Geo view kit (geo-view plan phase C): lazy bundle, x-gs::geo-map shell, Lab fixture. */
class GeoKitTest extends ThemingTestCase
{
    public function test_geo_engine_ships_as_a_lazy_bundle_not_in_the_main_script(): void
    {
        $out = $this->tempDir();
        $this->artisan('gs-theme:build', ['--output' => $out])->assertSuccessful();
        $manifest = json_decode(file_get_contents($out.'/manifest.json'), true);

        $this->assertMatchesRegularExpression('/^lazy\/geo\.[0-9a-f]{10}\.js$/', $manifest['lazy']['geo']);
        $this->assertMatchesRegularExpression('/^lazy\/geo-tools\.[0-9a-f]{10}\.js$/', $manifest['lazy']['geo-tools']);
        $this->assertStringNotContainsString('exportPng', file_get_contents($out.'/'.$manifest['lazy']['geo']));   // tools load only on maps that use them
        $geo = file_get_contents($out.'/'.$manifest['lazy']['geo']);
        $this->assertStringContainsString('GsGeoView', $geo);
        $main = file_get_contents($out.'/'.$manifest['js']);
        $this->assertStringNotContainsString('GsGeoView', $main);
        // The loader in the main bundle stays small (plan C exit: main bundle grows by < 1 KB).
        preg_match('/---- on-demand bundles.*?window\.GS\.scanLazy = scanLazy;/s', $main, $loader);
        $this->assertNotEmpty($loader);
        $this->assertLessThan(1500, strlen($loader[0]));

        $order = array_map('basename', app('gs.theme')->assets()->lazyBundles()['geo']);
        $this->assertSame('00-core.js', $order[0]);
        $this->assertSame('90-api.js', end($order));
        $this->assertSame(route('gs-theme.dev', ['file' => 'lazy-geo.js', 'v' => app('gs.theme')->devVersion()]), app('gs.theme')->lazyAssets()['geo']);
        $this->assertArrayHasKey('geo-tools', app('gs.theme')->lazyAssets());
    }

    public function test_lazy_bundle_names_are_checked(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app('gs.theme')->assets()->lazy('Bad Name', __FILE__);
    }

    public function test_geo_map_shell_embeds_config_payload_and_the_fallback_table(): void
    {
        $config = GeoLabFixture::config('lab-map', 'progress', ['boundary' => '/b', 'data' => '/d', 'details' => '/x'], 'en-US', ['map' => 'Map']);
        $payload = GeoLabFixture::payload(1, 'progress', true, 'en-US');
        $html = Blade::render('<x-gs::geo-map :config="$config" :payload="$payload"><table><tr><td>North</td></tr></table></x-gs::geo-map>', compact('config', 'payload'));

        $this->assertStringContainsString('data-gs-geo="lab-map"', $html);
        $this->assertStringContainsString('data-gs-lazy="geo"', $html);
        $this->assertStringContainsString('<script type="application/json" data-gs-geo-config>', $html);
        $this->assertStringContainsString('data-gs-geo-payload', $html);
        $this->assertStringContainsString('<div class="gs-geo__fallback" data-gs-geo-fallback><table>', $html);
        $this->assertStringNotContainsString('<x-gs::', $html);
        $this->assertStringNotContainsString('style=', $html);
        // @json escapes markup, so a name can never close the script element.
        $evil = GeoLabFixture::config('lab-evil', 'progress', ['boundary' => '/b', 'data' => '/d', 'details' => '/x'], 'en-US', ['map' => '</script><b>']);
        $this->assertStringNotContainsString('</script><b>', Blade::render('<x-gs::geo-map :config="$c" />', ['c' => $evil]));
    }

    public function test_fixture_shapes_share_borders_and_payload_covers_every_state(): void
    {
        $country = GeoLabFixture::childrenFile(GeoLabFixture::ROOT);
        $this->assertCount(4, $country['areas']);
        $this->assertSame(GeoLabFixture::VERSION, $country['version']);
        $this->assertNull(GeoLabFixture::childrenFile(1000));   // leaves have no children file

        $payload = GeoLabFixture::payload(GeoLabFixture::ROOT, 'progress', true, 'bn-BD');
        $states = array_count_values(array_column($payload['areas'], 'state'));
        ksort($states);
        $this->assertSame(['no_access' => 1, 'ok' => 2, 'partial' => 1], $states);
        $this->assertArrayNotHasKey('value', $payload['areas']['13']);   // no_access: no value, no count
        $this->assertArrayNotHasKey('offices', $payload['areas']['13']);
        $this->assertSame('নমুনাদেশ', $payload['area']['name']);
        $this->assertSame('gs-geo-data/1', $payload['contract']);
    }

    public function test_fixture_flat_file_search_and_level_payload(): void
    {
        $flat = GeoLabFixture::flatFile(2);
        $this->assertCount(12, $flat['areas']);
        $this->assertSame(10, $flat['areas'][0]['parent']);
        $payload = GeoLabFixture::payload(GeoLabFixture::ROOT, 'progress', false, 'en-US', 'lab.synthetic', 2);
        $this->assertSame(2, $payload['flat']);
        $this->assertCount(12, $payload['areas']);
        $this->assertSame([1310], array_column(array_filter(GeoLabFixture::search('South 2a', 'en-US'), fn ($r) => $r['level'] === 3), 'id'));
        $config = GeoLabFixture::config('lab-p', 'progress', ['boundary' => '/b', 'data' => '/d', 'details' => '/x', 'search' => '/s'], 'en-US', []);
        $this->assertTrue($config['toolbar']['search'] && $config['toolbar']['export'] && $config['toolbar']['fullscreen']);
        $this->assertSame(2, $config['levels']['flat']);
    }

    public function test_fixture_endpoints_follow_lab_access(): void
    {
        $call = function (string $method, array $query = [], ...$args) {
            $request = Request::create('/gov/theme-lab/geo-fixture', 'GET', $query);
            $request->headers->set('Accept', 'application/json');
            $request->setUserResolver(fn () => auth()->user());
            app()->instance('request', $request);

            return app(ThemeLabController::class)->$method($request, ...$args);
        };
        $this->actor('company_admin');
        $this->assertSame(403, $call('geoData', ['area' => 1])->getStatusCode());

        $this->actor('superuser');
        $this->assertSame(4, count($call('geoData', ['area' => 1, 'states' => 1])->getData(true)['areas']));
        $this->assertSame(GeoLabFixture::VERSION, $call('geoChildren', [], 10)->getData(true)['version']);
        $this->assertNotEmpty($call('geoDetails', ['area' => 10])->getData(true)['offices']);
        try {
            $call('geoChildren', [], 1000);
            $this->fail('Expected 404');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }
}
