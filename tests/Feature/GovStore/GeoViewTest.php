<?php

namespace Tests\Feature\GovStore;

use GovStore\GeoView\Config\GeoViewConfigResolver;
use GovStore\GeoView\Registry\GeoPresetRegistry;
use GovStore\GeoView\Support\JsonSchema;
use Illuminate\Support\Facades\Blade;
use Tests\Feature\GovStore\Concerns\GeoDataSourceContract;
use Tests\Feature\GovStore\GeoView\FixtureProgrammeSource;
use Tests\Feature\GovStore\GeoView\GeoViewTestCase;

/** Geo view wiring (plan phase D): resolver, aggregation, scope mask, data API and <x-geoview::map>. */
class GeoViewTest extends GeoViewTestCase
{
    use GeoDataSourceContract;

    private const PRESET = [
        'intent' => 'progress',
        'source' => ['key' => 'fixture.programme', 'params' => ['initiative', 'targets', 'code']],
        'layers' => ['default' => ['delivered_share', 'received_qty'], 'allow' => ['delivered_share', 'received_qty', 'value', 'waiting_offices']],
        'levels' => ['start' => 'auto', 'min' => 1, 'max' => 3, 'urban' => 'colour'],
        'size' => ['height' => 420, 'aspect' => null],
        'locked' => ['source.key', 'levels.max'],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        app(GeoPresetRegistry::class)->merge(['fixture.programme' => self::PRESET]);
    }

    protected function geoSourceKey(): string
    {
        return 'fixture.programme';
    }

    protected function geoValidParams(): array
    {
        return ['initiative' => 30, 'targets' => 1];
    }

    protected function geoActAsPermitted(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
    }

    protected function geoActWithoutAbility(): void
    {
        $this->actAs(4);
    }

    protected function geoActWithoutObjectAccess(): void
    {
        $this->actAs(FixtureProgrammeSource::OUTSIDER);
    }

    protected function geoAreaOutsideScope(): ?int
    {
        return null;
    }

    protected function geoRatioLayer(): ?string
    {
        return 'delivered_share';
    }

    // ---- resolver --------------------------------------------------------------------------------------------

    public function test_resolver_merges_layers_in_order_and_honours_locks(): void
    {
        $result = app(GeoViewConfigResolver::class)->resolve('fixture.programme', [
            'id' => 'programme-map',
            'params' => ['initiative' => 30, 'code' => 104, 'secret' => 'x', 'q' => null],
            'levels' => ['max' => 4, 'start' => 'root'],
            'size' => ['height' => 500],
        ]);
        $config = $result['config'];
        $this->assertSame([], JsonSchema::validate(JsonSchema::contract('gs-geo-config.schema.json'), $config));
        $this->assertSame('progress', $config['intent']);
        $this->assertSame(['type' => 'sequential', 'steps' => 5, 'domain' => [0, 1]], $config['scale']);   // from the intent
        $this->assertSame(['delivered_share', 'received_qty'], $config['layers']['default']);               // lists stay lists
        $this->assertSame(3, $config['levels']['max']);                                                     // locked by the preset
        $this->assertSame('root', $config['levels']['start']);                                              // host may change
        $this->assertSame(500, $config['size']['height']);
        $this->assertSame(['initiative' => 30, 'code' => 104], $config['source']['params']);               // undeclared params dropped
        $this->assertSame([['key' => 'levels.max', 'layer' => 'host', 'lock' => 'levels.max']], $result['blocked']);
        $this->assertSame('/gov-store/api/geo-view/sources/fixture.programme/data', $config['endpoints']['data']);
        $this->assertSame(9358, $config['boundary']['root']);
        $this->assertStringStartsWith('/vendor/geo-areas/boundaries/bd-boundaries-', $config['boundary']['base_url']);
        $this->assertArrayHasKey('up_to', $config['strings']);
    }

    public function test_resolver_rejects_unknown_keys_in_testing_and_prunes_them_in_production(): void
    {
        try {
            app(GeoViewConfigResolver::class)->resolve('fixture.programme', ['id' => 'm', 'colour' => 'red']);
            $this->fail('expected an exception');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('colour', $e->getMessage());
        }
        $this->app['env'] = 'production';
        $config = app(GeoViewConfigResolver::class)->resolve('fixture.programme', ['id' => 'm', 'colour' => 'red', 'toolbar' => ['rainbow' => true]])['config'];
        $this->assertArrayNotHasKey('colour', $config);
        $this->assertArrayNotHasKey('rainbow', $config['toolbar']);
        $this->app['env'] = 'testing';
        $this->expectException(\InvalidArgumentException::class);
        app(GeoViewConfigResolver::class)->resolve('nope', []);
    }

    // ---- aggregation and layers (plan §11.2 on the pilot shape) ------------------------------------------------

    public function test_pilot_shape_opens_on_the_country_and_falls_back_to_received_quantity(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
        $p = $this->payload('fixture.programme', ['initiative' => 30, 'area' => 'auto', 'fallback' => 'delivered_share,received_qty']);

        $this->assertSame(9358, $p['area']['id']);                       // offices in four divisions → the country
        $this->assertSame('received_qty', $p['layer']['key']);
        $this->assertSame('delivered_share', $p['layer']['fallback_from']);
        $this->assertSame('No office targets in this programme; showing received quantity', $p['layer']['fallback_note']);
        $this->assertFalse(collect($p['layers'])->firstWhere('key', 'delivered_share')['applicable']);
        $coloured = collect($p['areas'])->filter(fn ($a) => $a['state'] === 'ok')->map(fn ($a) => $a['value'])->all();
        $this->assertSame(['1069' => 55, '2352' => 55, '3142' => 55, '4730' => 55], collect($coloured)->sortKeys()->all());
        $this->assertSame(['min' => 55, 'max' => 55, 'national' => 220, 'total' => 220], $p['stats']);
        $this->assertSame(4, collect($p['areas'])->where('state', 'no_data')->count());
        $this->assertSame(['offices' => 4, 'placed' => 4, 'verified' => 1, 'rolled_up' => 0], $p['coverage']);   // office 175 masked
    }

    public function test_office_on_the_viewed_area_is_counted_at_this_level(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
        $dhaka = $this->payload('fixture.programme', ['initiative' => 30, 'area' => 3142, 'layer' => 'received_qty']);
        $this->assertSame(1, $dhaka['here']['offices']);
        $this->assertSame(55, $dhaka['here']['value']);
        $this->assertSame([], collect($dhaka['areas'])->where('state', 'ok')->all());
        $this->assertSame(['Bangladesh', 'Dhaka'], array_column($dhaka['area']['trail'], 'name'));

        $barishal = $this->payload('fixture.programme', ['initiative' => 30, 'area' => 2352, 'layer' => 'received_qty']);
        $this->assertSame('ok', $barishal['areas']['2390']['state']);
        $this->assertSame(1, $barishal['areas']['2390']['offices']);
        $details = json_decode($this->api('details', 'fixture.programme', ['initiative' => 30, 'area' => 2390, 'layer' => 'received_qty'])->getContent(), true);
        $this->assertSame([171], array_column($details['offices'], 'id'));   // office 175 is outside the programme
        $this->assertSame('/gov-store/admin/tracking/initiatives/30/offices/171', $details['offices'][0]['link']);
    }

    public function test_filters_restrict_facts_and_unknown_values_are_rejected(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
        $p = $this->payload('fixture.programme', ['initiative' => 30, 'area' => 'auto', 'layer' => 'received_qty', 'filters' => ['code' => '104']]);
        $this->assertSame(2390, $p['area']['id']);   // one office left: auto opens on its district
        $this->assertSame('104', $p['filters'][0]['value']);
        $this->assertSame(422, $this->api('data', 'fixture.programme', ['initiative' => 30, 'filters' => ['code' => '1']])->getStatusCode());
    }

    public function test_ratio_count_money_display_and_suppression(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
        $p = $this->payload('fixture.programme', ['initiative' => 30, 'targets' => 1, 'area' => 9358, 'layer' => 'delivered_share']);
        $this->assertEqualsWithDelta(0.3, $p['areas']['2352']['value'], 1e-9);           // 30 / 100
        $this->assertEqualsWithDelta(60 / 400, $p['stats']['total'], 1e-9);              // Σnum/Σden, not mean of %
        $money = $this->payload('fixture.programme', ['initiative' => 30, 'area' => 9358, 'layer' => 'value']);
        $this->assertSame('৳2,575,000', $money['areas']['2352']['display']);
        $waiting = $this->payload('fixture.programme', ['initiative' => 30, 'targets' => 1, 'area' => 9358, 'layer' => 'waiting_offices']);
        $this->assertSame('suppressed', $waiting['areas']['1069']['state']);              // 1 office < suppress_below 2
        $this->assertArrayNotHasKey('value', array_filter($waiting['areas']['1069'], fn ($v) => $v !== null));
    }

    public function test_etag_revalidation_answers_304(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
        $first = $this->api('data', 'fixture.programme', ['initiative' => 30, 'area' => 'auto']);
        $this->assertTrue($first->headers->hasCacheControlDirective('private') && $first->headers->hasCacheControlDirective('no-cache'));
        $etag = $first->headers->get('ETag');
        $request = \Illuminate\Http\Request::create('/x', 'GET', ['initiative' => 30, 'area' => 'auto']);
        $request->headers->set('If-None-Match', $etag);
        $request->setUserResolver(fn () => auth()->user());
        $this->assertSame(304, app(\GovStore\GeoView\Http\Controllers\GeoDataController::class)->data($request, 'fixture.programme')->getStatusCode());
    }

    // ---- scope (two locks) -------------------------------------------------------------------------------------

    public function test_area_grain_scope_marks_partial_and_no_access_and_withholds_partial_values(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
        $country = $this->payload('fixture.areas', ['area' => 'auto']);
        $this->assertSame(1246, $country['area']['id']);   // auto opens on the scope (Bogura)
        $root = $this->payload('fixture.areas', ['area' => 9358]);
        $this->assertSame('partial', $root['areas']['1069']['state']);
        $this->assertNull($root['areas']['1069']['value']);                 // area grain: withheld
        $this->assertTrue($root['areas']['1069']['drillable']);
        $this->assertSame('no_access', $root['areas']['3142']['state']);
        $this->assertArrayNotHasKey('value', $root['areas']['3142']);
        $this->assertTrue($root['scope']['partial']);
        $outside = $this->payload('fixture.areas', ['area' => 3142]);
        $this->assertSame(['no_access'], array_values(array_unique(array_column($outside['areas'], 'state'))));
        $this->assertSame([], json_decode($this->api('details', 'fixture.areas', ['area' => 3142])->getContent(), true)['offices']);
        $inside = $this->payload('fixture.areas', ['area' => 1246]);
        $this->assertSame(['ok'], array_values(array_unique(array_column($inside['areas'], 'state'))));
    }

    public function test_unknown_source_is_404(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
        $this->assertSame(404, $this->api('data', 'nope.source', ['area' => 'auto'])->getStatusCode());
        $this->assertSame(404, $this->api('data', '../etc', ['area' => 'auto'])->getStatusCode());
    }

    // ---- component and doctor ---------------------------------------------------------------------------------

    public function test_map_component_embeds_config_first_payload_and_server_table(): void
    {
        $this->actAs(FixtureProgrammeSource::MEMBER);
        $html = Blade::render('<x-geoview::map id="programme-map" preset="fixture.programme" :params="[\'initiative\' => 30]" bind-filters=".gs-tracking-filters" />');
        $this->assertStringContainsString('data-gs-geo="programme-map"', $html);
        $this->assertStringContainsString('data-gs-geo-payload', $html);
        $this->assertStringContainsString('<caption>Items received · Bangladesh</caption>', $html);
        $this->assertStringContainsString('&quot;bind_filters&quot;:&quot;.gs-tracking-filters&quot;', htmlspecialchars(html_entity_decode($html)));
        $this->assertStringNotContainsString('<x-', $html);

        $this->actAs(FixtureProgrammeSource::OUTSIDER);
        $denied = Blade::render('<x-geoview::map id="programme-map" preset="fixture.programme" :params="[\'initiative\' => 30]" />');
        $this->assertStringNotContainsString('data-gs-geo-payload', $denied);
        $this->assertStringContainsString(__('gs-theme::kit.geo.denied'), $denied);
    }

    public function test_doctor_checks_presets_and_sources(): void
    {
        $this->artisan('geo-view:doctor')->assertExitCode(0);
        app(GeoPresetRegistry::class)->merge(['broken' => ['source' => ['key' => 'missing.source']]]);
        $this->artisan('geo-view:doctor')->assertExitCode(1);
    }
}
