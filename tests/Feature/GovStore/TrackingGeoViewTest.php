<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use GovStore\GeoAreas\Services\GeoTree;
use GovStore\GeoView\Services\OfficeLocator;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingDelivery;
use GovStore\Tracking\Services\TrackingMonitorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Tests\Feature\GovStore\Concerns\GeoDataSourceContract;

/**
 * The tracking programme map (geo-view plan phase E, §11.2) on the pilot's shape: a product-based programme
 * with a regional office on the Dhaka division and district offices in Patuakhali, Bogura and Jamalpur.
 * Every inherited workflow test also runs here, like the other tracking suites.
 */
class TrackingGeoViewTest extends TrackingWorkflowTest
{
    use GeoDataSourceContract;

    private const OFFICES = [171 => 3142, 172 => 2390, 173 => 1246, 174 => 4949];

    private ?Initiative $pilot = null;

    private array $codes = [];

    /** Pilot data is created only for the geo tests, so the inherited workflow tests keep their own fixtures. */
    private function pilot(): Initiative
    {
        if ($this->pilot) {
            return $this->pilot;
        }
        Schema::table('gov_geo_areas', function (Blueprint $t) {
            $t->string('geo_type', 30)->nullable();
            $t->integer('GeoLevel')->nullable();
        });
        $rows = [];
        $handle = fopen(base_path('packages/gov-store/geo-areas/src/database/data/geo_areas.csv'), 'r');
        while (($r = fgetcsv($handle)) !== false) {
            $id = (int) preg_replace('/\D/', '', $r[0]);
            if ($id && is_numeric($r[8] ?? null)) {
                $rows[] = ['GeoAreaId' => $id, 'hid' => $r[1], 'geo_type' => $r[2], 'parent_geo_code' => (int) $r[3], 'geo_code' => (int) $r[4], 'bn_name' => $r[5], 'en_name' => $r[7], 'GeoLevel' => (int) $r[8]];
            }
        }
        fclose($handle);
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('gov_geo_areas')->insert($chunk);
        }
        foreach (self::OFFICES as $office => $area) {
            DB::table('locations')->insert(['id' => $office, 'name' => 'Pilot office '.$office, 'company_id' => 20]);
            DB::table('gov_location_profiles')->insert(['location_id' => $office, 'geo_area_id' => $area]);
        }
        app(GeoTree::class)->forget();
        app(OfficeLocator::class)->forget();
        // The ability itself is GovAccess's business; here it only separates "no ability" (user 4) from the rest.
        Gate::define('tracking.programmes.view', fn ($user) => (int) $user->getAuthIdentifier() !== 4);
        $this->pilot = $this->pilotProgramme();
        app()->setLocale('en-US');

        return $this->pilot;
    }

    /** A product-based programme: no office allocations, 55 items received at each office. */
    private function pilotProgramme(): Initiative
    {
        $initiative = Initiative::withoutGlobalScopes()->create(['title' => 'Pilot programme', 'owner_company_id' => 20, 'primary_funding' => 'ADP', 'status' => 'Active']);
        $fund = DB::table('gov_funding_types')->insertGetId(['name' => 'Fund', 'primary_type' => 'ADP']);
        DB::table('gov_tracking_operation_units')->insert(['initiative_id' => $initiative->id, 'user_id' => 1, 'designation' => 'MONITOR']);
        foreach (self::OFFICES as $office => $area) {
            $code = TrackingCode::create(['initiative_id' => $initiative->id, 'funding_type_id' => $fund, 'tracking_code' => 'EXP-ADP-'.$office,
                'task_title' => 'Lab kits '.$office, 'specificity_level' => '2_CATEGORY', 'fiscal_year' => '2026-2027', 'status' => 'ACTIVE', 'activated_at' => '2026-07-01']);
            DB::table('gov_tracking_targets')->insert(['tracking_code_id' => $code->id, 'category_id' => 1, 'planned_qty' => 880]);
            $delivery = DB::table('gov_tracking_deliveries')->insertGetId(['tracking_code_id' => $code->id, 'delivery_key' => hash('sha256', 'pilot'.$office),
                'created_at' => '2026-10-15', 'receipt_snapshot' => json_encode(['quantity' => 55, 'total_cost' => 2575000, 'category_id' => 1, 'location_id' => $office,
                    'grn' => 'GRN-'.$office, 'occurred_at' => '2026-10-15', 'supplier_id' => null, 'manufacturer_id' => null, 'model_id' => null])]);
            DB::table('gov_tracking_associations')->insert(['tracking_code_id' => $code->id, 'delivery_id' => $delivery, 'category_id' => 1, 'location_id' => $office,
                'quantity' => 55, 'status' => 'ACTIVE', 'associatable_type' => TrackingDelivery::class, 'associatable_id' => $delivery,
                'created_at' => '2026-10-15', 'updated_at' => '2026-10-15']);
            $this->codes[$office] = $code->id;
        }

        return $initiative;
    }

    private function geoUser(int $id): void
    {
        $this->pilot();
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->location_id = 10;
        $user->shouldReceive('isSuperUser')->andReturn(false);
        $user->shouldReceive('hasAccess')->andReturn(false);
        $user->shouldReceive('getAuthIdentifier')->andReturn($id);
        auth()->setUser($user);
    }

    protected function geoSourceKey(): string
    {
        return 'tracking.programme';
    }

    protected function geoValidParams(): array
    {
        return ['initiative' => $this->pilot()->id];
    }

    protected function geoActAsPermitted(): void
    {
        $this->geoUser(1);
    }

    protected function geoActWithoutAbility(): void
    {
        $this->geoUser(4);
    }

    protected function geoActWithoutObjectAccess(): void
    {
        $this->geoUser(3);   // an ICT officer outside the programme: no operation unit, not the owner company admin
    }

    protected function geoAreaOutsideScope(): ?int
    {
        return null;   // the programme key covers every area (tracking plan §6)
    }

    protected function geoHiddenObjectName(): ?string
    {
        return 'Pilot programme';
    }

    public function test_pilot_acceptance_country_view_fallback_layer_and_drill(): void
    {
        $this->geoUser(1);
        $country = $this->geoPayload('tracking.programme', ['initiative' => $this->pilot->id, 'area' => 'auto', 'fallback' => 'delivered_share,received_qty']);
        $this->assertSame(9358, $country['area']['id']);                                   // offices in four divisions
        $this->assertSame('received_qty', $country['layer']['key']);                       // no office targets
        $this->assertSame(__('govtracking::general.geo.no_office_targets'), $country['layer']['fallback_note']);
        $ok = collect($country['areas'])->where('state', 'ok');
        $this->assertSame([1069, 2352, 3142, 4730], $ok->keys()->map(fn ($k) => (int) $k)->sort()->values()->all());
        $this->assertSame([55], $ok->pluck('value')->unique()->values()->all());
        $this->assertSame(4, collect($country['areas'])->where('state', 'no_data')->count());

        $dhaka = $this->geoPayload('tracking.programme', ['initiative' => $this->pilot->id, 'area' => 3142, 'layer' => 'received_qty']);
        $this->assertSame([], collect($dhaka['areas'])->where('state', 'ok')->all());   // all districts no_data
        $this->assertSame(1, $dhaka['here']['offices']);                                  // the regional office

        $barishal = $this->geoPayload('tracking.programme', ['initiative' => $this->pilot->id, 'area' => 2352, 'layer' => 'received_qty']);
        $this->assertSame('ok', $barishal['areas']['2390']['state']);
        $details = json_decode($this->geoApi('details', 'tracking.programme', ['initiative' => $this->pilot->id, 'area' => 2390])->getContent(), true);
        $this->assertSame([172], array_column($details['offices'], 'id'));
        $this->assertSame(route('gov.tracking.initiatives.offices.show', [$this->pilot->id, 172], false), $details['offices'][0]['link']);
    }

    public function test_code_filter_narrows_the_map_to_its_office(): void
    {
        $this->geoUser(1);
        $p = $this->geoPayload('tracking.programme', ['initiative' => $this->pilot->id, 'area' => 'auto', 'layer' => 'received_qty', 'filters' => ['code' => (string) $this->codes[172]]]);
        $this->assertSame(2352, $p['area']['id']);                                          // Patuakhali is drawn inside Barishal
        $this->assertSame(1, $p['coverage']['offices']);
        $code = $this->geoPayload('tracking.programme', ['initiative' => $this->pilot->id, 'code' => $this->codes[173], 'area' => 9358, 'layer' => 'received_qty']);
        $this->assertSame([1069], array_keys(array_filter($code['areas'], fn ($a) => $a['state'] === 'ok')));
    }

    public function test_monitor_page_embeds_the_map_with_server_table_and_no_tile_grid(): void
    {
        $this->geoUser(1);
        app('view')->getDispatcher()->forget('composing: layouts.default');
        $layoutDir = storage_path('framework/testing/tracking-geo/layouts');
        if (! is_dir($layoutDir)) {
            mkdir($layoutDir, 0777, true);
        }
        file_put_contents($layoutDir.'/default.blade.php', '@yield("content")');
        app('view')->getFinder()->prependLocation(dirname($layoutDir));
        $monitor = app(TrackingMonitorService::class)->programme($this->pilot);
        $html = view('govtracking::initiatives.monitor', ['initiative' => $this->pilot, 'monitor' => $monitor])->render();
        $this->assertStringContainsString('data-gs-geo="programme-map"', $html);
        $this->assertStringContainsString('data-gs-lazy="geo"', $html);
        $this->assertStringContainsString('<caption>'.__('govtracking::general.geo.received_qty').' · Bangladesh</caption>', $html);
        $this->assertStringContainsString('"bind_filters":".gs-tracking-filters"', $html);
        $this->assertStringNotContainsString('gs-tile-map', $html);
        $this->assertStringNotContainsString('<x-', $html);
        unlink($layoutDir.'/default.blade.php');
    }
}
