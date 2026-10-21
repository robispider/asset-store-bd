<?php

namespace Tests\Feature\GovStore;

use GovStore\GeoAreas\Services\GeoTree;
use GovStore\GeoView\Http\Controllers\GeoMonitorController;
use GovStore\GeoView\Registry\GeoPresetRegistry;
use GovStore\GeoView\Registry\GeoSourceRegistry;
use GovStore\GeoView\Services\OfficeLocator;
use GovStore\GeoView\Support\JsonSchema;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\GovStore\GeoView\GeoViewTestCase;

/**
 * The national geo monitor (geo-view plan phase F) with the organization office source: geo-monitoring §8
 * checks, re-pointed at the geo view data API.
 */
class GeoMonitoringTest extends GeoViewTestCase
{
    use Concerns\GeoDataSourceContract;

    private const ICT = 7;

    private int $boguraUpazila;

    protected function setUp(): void
    {
        parent::setUp();
        DB::table('gov_location_profiles')->delete();
        Schema::create('locations', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('gov_ict_jurisdictions', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->unsignedInteger('geo_area_id');
        });
        $this->boguraUpazila = app(GeoTree::class)->childIds(1246)[0];
        $offices = [201 => [1246, true], 202 => [$this->boguraUpazila, false], 203 => [3142, false], 204 => [null, false], 205 => [2390, false]];
        foreach ($offices as $id => [$area, $verified]) {
            DB::table('locations')->insert(['id' => $id, 'name' => 'Office '.$id]);
            if ($area) {
                DB::table('gov_location_profiles')->insert(['location_id' => $id, 'geo_area_id' => $area, 'geo_area_verified_at' => $verified ? now() : null]);
            }
        }
        DB::table('gov_ict_jurisdictions')->insert(['user_id' => self::ICT, 'geo_area_id' => 1246]);
        app(OfficeLocator::class)->forget();
        Gate::define('geo.monitor.view', fn ($user) => (int) $user->getAuthIdentifier() !== 4);
        Gate::define('organization.office.manage', fn ($user) => (int) $user->getAuthIdentifier() === 1);
        app(GeoSourceRegistry::class)->register(\GovStore\Organization\Geo\OrganizationOfficesGeoSource::class, 'organization.offices');
        app(GeoPresetRegistry::class)->merge(require base_path('packages/gov-store/organization/src/config/geo-view-presets.php'));
        $this->national();
    }

    private function national(): void
    {
        $context = app(TenantContext::class);
        $context->reset();
        $context->isActive = true;
        $context->isGlobal = true;
        $this->actAs(1);
    }

    private function ictOfficer(): void
    {
        $context = app(TenantContext::class);
        $context->reset();
        $context->isActive = true;
        $context->allowedLocationIds = [201, 202];
        $this->actAs(self::ICT);
    }

    protected function geoSourceKey(): string
    {
        return 'organization.offices';
    }

    protected function geoValidParams(): array
    {
        return [];
    }

    protected function geoActAsPermitted(): void
    {
        $this->ictOfficer();
    }

    protected function geoActWithoutAbility(): void
    {
        $this->actAs(4);
    }

    protected function geoActWithoutObjectAccess(): void
    {
        $this->actAs(4);   // the office network has no object of its own: without the ability nothing is shown
    }

    protected function geoAreaOutsideScope(): ?int
    {
        return 3142;
    }

    protected function geoRatioLayer(): ?string
    {
        return 'verified_share';
    }

    public function test_contract_object_without_access_is_404_without_naming_it(): void
    {
        $this->geoActWithoutObjectAccess();
        $this->assertSame(403, $this->geoApi('data', 'organization.offices', ['area' => 'auto'])->getStatusCode());
    }

    public function test_contract_tampered_params_are_422(): void
    {
        $this->ictOfficer();
        $this->assertSame(422, $this->geoApi('data', 'organization.offices', ['area' => '1 or 1=1'])->getStatusCode());
        $this->assertSame(422, $this->geoApi('data', 'organization.offices', ['area' => 'auto', 'layer' => 'Robert"); drop'])->getStatusCode());
    }

    public function test_national_view_counts_offices_rolls_up_ratios_and_reports_unplaced(): void
    {
        $p = $this->geoPayload('organization.offices', ['area' => 9358, 'layer' => 'offices']);
        $this->assertSame(2, $p['areas']['1069']['value']);       // Bogura district + an upazila in Rajshahi
        $this->assertSame(1, $p['areas']['3142']['value']);
        $this->assertSame(1, $p['areas']['2352']['value']);
        $this->assertSame(1, $p['unplaced']['offices']);
        $this->assertSame(['offices' => 5, 'placed' => 4, 'verified' => 1, 'rolled_up' => 0], $p['coverage']);

        $ratio = $this->geoPayload('organization.offices', ['area' => 9358, 'layer' => 'verified_share']);
        $this->assertSame(1, $ratio['areas']['1069']['num']);
        $this->assertSame(2, $ratio['areas']['1069']['den']);
        $this->assertEqualsWithDelta(0.5, $ratio['areas']['1069']['value'], 1e-9);
        $this->assertEqualsWithDelta(0.25, $ratio['stats']['total'], 1e-9);       // placed offices in the country
        $this->assertEqualsWithDelta(0.2, $ratio['stats']['national'], 1e-9);     // every office, unplaced included
        $details = json_decode($this->geoApi('details', 'organization.offices', ['area' => 1246])->getContent(), true);
        $this->assertSame([201, 202], array_column($details['offices'], 'id'));
        $this->assertTrue($details['offices'][0]['here']);
        $this->assertSame(route('gov.org.hub.show', 201, false), $details['offices'][0]['link']);
    }

    public function test_ict_officer_starts_on_the_jurisdiction_and_sees_nothing_outside_it(): void
    {
        $this->ictOfficer();
        $start = $this->geoPayload('organization.offices', ['area' => 'auto']);
        $this->assertSame(1246, $start['area']['id']);                              // their jurisdiction, not Rajshahi
        $this->assertSame(1, $start['here']['offices']);
        $this->assertSame(1, $start['areas'][(string) $this->boguraUpazila]['value']);

        $country = $this->geoPayload('organization.offices', ['area' => 9358, 'layer' => 'offices']);
        $this->assertSame('partial', $country['areas']['1069']['state']);
        $this->assertSame(2, $country['areas']['1069']['value']);                   // only their offices are counted
        foreach (['3142', '2352', '1', '2759'] as $other) {
            $this->assertSame('no_access', $country['areas'][$other]['state']);
            $this->assertArrayNotHasKey('value', $country['areas'][$other]);
            $this->assertArrayNotHasKey('offices', $country['areas'][$other]);
        }
        $this->assertSame(0, $country['unplaced']['offices']);                      // office 204 is not theirs
        $this->assertSame([], JsonSchema::validate(JsonSchema::contract('gs-geo-data.schema.json'), $country));
        $details = json_decode($this->geoApi('details', 'organization.offices', ['area' => 1246])->getContent(), true);
        $this->assertNull($details['offices'][0]['link']);                         // no office-manage ability, no link
    }

    public function test_office_admin_scope_is_the_office_area(): void
    {
        $context = app(TenantContext::class);
        $context->reset();
        $context->isActive = true;
        $context->allowedLocationIds = [205];
        $this->actAs(9);
        $start = $this->geoPayload('organization.offices', ['area' => 'auto']);
        $this->assertSame(2390, $start['area']['id']);   // the office's district
        $country = $this->geoPayload('organization.offices', ['area' => 9358]);
        $this->assertSame('partial', $country['areas']['2352']['state']);
        $this->assertSame('no_access', $country['areas']['1069']['state']);
    }

    public function test_flat_mode_shows_every_district_and_counts_offices_by_district(): void
    {
        $p = $this->geoPayload('organization.offices', ['area' => 9358, 'level' => 2, 'layer' => 'offices']);
        $this->assertSame(2, $p['flat']);
        $this->assertSame(2, $p['level']);
        $this->assertCount(64, $p['areas']);
        $this->assertStringEndsWith('/flat/level-2.json', $p['boundary']['children']);
        $this->assertSame(2, $p['areas']['1246']['value']);     // Bogura: the district office and its upazila office
        $this->assertSame(1, $p['areas']['2390']['value']);
        $this->assertSame(1, $p['here']['offices']);             // the Dhaka division office sits above district level
        $this->assertSame([], JsonSchema::validate(JsonSchema::contract('gs-geo-data.schema.json'), $p));
        $this->assertSame(422, $this->geoApi('data', 'organization.offices', ['area' => 9358, 'level' => 4])->getStatusCode());
        // Flat mode is a country view: elsewhere the level is ignored.
        $this->assertNull($this->geoPayload('organization.offices', ['area' => 1069, 'level' => 2])['flat']);
    }

    public function test_union_level_opens_below_an_upazila(): void
    {
        $p = $this->geoPayload('organization.offices', ['area' => $this->boguraUpazila]);
        $this->assertSame(4, $p['level']);
        $this->assertNotEmpty($p['areas']);
        $this->assertContains(app(GeoTree::class)->node((int) array_key_first($p['areas']))['type'], ['union', 'pourasabha']);
    }

    public function test_monitor_page_lists_national_presets_and_has_a_breadcrumb(): void
    {
        $route = Route::getRoutes()->getByName('gov.geo-monitor.index');
        $this->assertContains('gov.can:geo.monitor.view', $route->gatherMiddleware());
        $this->assertSame(['superuser', 'ict_officer', 'company_admin', 'office_admin'], config('govstore-abilities')['geo.monitor.view']['roles']);
        $this->assertNotSame('tenantops::access.abilities.geo_monitor_view', __('tenantops::access.abilities.geo_monitor_view'));

        $layoutDir = storage_path('framework/testing/geo-monitor/layouts');
        if (! is_dir($layoutDir)) {
            mkdir($layoutDir, 0777, true);
        }
        file_put_contents($layoutDir.'/default.blade.php', '@yield("content")');
        app('view')->getFinder()->prependLocation(dirname($layoutDir));
        app('view')->getDispatcher()->forget('composing: layouts.default');
        $request = Request::create('/gov-store/geo-monitor');
        $request->setUserResolver(fn () => auth()->user());
        $view = app(GeoMonitorController::class)->index($request, app(GeoPresetRegistry::class), app(GeoSourceRegistry::class));
        $this->assertSame('organization.offices', $view->getData()['current']);
        $html = $view->render();
        $this->assertStringContainsString('data-gs-geo="geo-monitor"', $html);
        $this->assertStringContainsString('data-gs-geo-payload', $html);
        $this->assertStringNotContainsString('<x-', $html);
        unlink($layoutDir.'/default.blade.php');
    }
}
