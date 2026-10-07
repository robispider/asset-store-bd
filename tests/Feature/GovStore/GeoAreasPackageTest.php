<?php

namespace Tests\Feature\GovStore;

use GovStore\GeoAreas\Http\Controllers\GeoAreaController;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\GeoAreas\Services\GeoAreaService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class GeoAreasPackageTest extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'LOG_CHANNEL' => 'stderr'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require __DIR__.'/../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('gov_geo_areas', function (Blueprint $table) {
            $table->unsignedInteger('GeoAreaId')->primary();
            $table->string('hid')->nullable()->index();
            $table->string('geo_type', 30)->index();
            $table->integer('parent_geo_code')->nullable();
            $table->integer('geo_code');
            $table->string('bn_name');
            $table->string('domain')->nullable();
            $table->string('en_name');
            $table->integer('GeoLevel');
            $table->timestamps();
        });
        DB::table('gov_geo_areas')->insert([
            ['GeoAreaId' => 1, 'hid' => '/36/', 'geo_type' => 'divison', 'geo_code' => 36, 'bn_name' => 'চট্টগ্রাম', 'en_name' => 'Chattogram', 'GeoLevel' => 1],
            ['GeoAreaId' => 2, 'hid' => '/36/28/', 'geo_type' => 'district', 'geo_code' => 28, 'bn_name' => 'কুমিল্লা জেলা', 'en_name' => 'Cumilla District', 'GeoLevel' => 2],
            ['GeoAreaId' => 3, 'hid' => '/36/28/29/', 'geo_type' => 'upazilla', 'geo_code' => 29, 'bn_name' => 'দেবিদ্বার উপজেলা', 'en_name' => 'Debidwar Upazila', 'GeoLevel' => 3],
            ['GeoAreaId' => 4, 'hid' => '/36/28/29/32/', 'geo_type' => 'union', 'geo_code' => 32, 'bn_name' => 'সুবিল ইউনিয়ন', 'en_name' => 'Subil', 'GeoLevel' => 4],
        ]);
    }

    public function test_typeahead_returns_locale_name_and_both_names_with_canonical_type(): void
    {
        app()->setLocale('en-US');
        $english = app(GeoAreaController::class)->search(Request::create('/', 'GET', ['q' => 'Chatt', 'types' => ['division']]), app(GeoAreaService::class));
        $this->assertSame('Chattogram', $english->getData(true)[0]['text']);
        $this->assertSame('চট্টগ্রাম', $english->getData(true)[0]['bn_name']);
        $this->assertSame('division', $english->getData(true)[0]['geo_type']);

        app()->setLocale('bn-BD');
        $bangla = app(GeoAreaController::class)->search(Request::create('/', 'GET', ['q' => 'চট্টগ্রাম']), app(GeoAreaService::class));
        $this->assertSame('চট্টগ্রাম', $bangla->getData(true)[0]['text']);
        $this->assertSame('Chattogram', $bangla->getData(true)[0]['en_name']);
    }

    public function test_empty_non_ajax_search_returns_json_instead_of_terminating_the_request(): void
    {
        $response = app(GeoAreaController::class)->search(Request::create('/', 'GET'), app(GeoAreaService::class));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertJson($response->getContent());
        $this->assertCount(4, $response->getData(true));
    }

    public function test_search_uses_prefix_matching_type_alias_hierarchy_and_safe_limit(): void
    {
        $service = app(GeoAreaService::class);
        $this->assertCount(0, $service->search('illa', ['district']));
        $this->assertCount(1, $service->search('Cum', ['district'], '/36/'));
        $this->assertCount(1, $service->search('', ['divison']));
        $this->assertCount(4, $service->search('', [], null, 500));
    }

    public function test_historical_geo_migration_never_drops_an_existing_reference_table(): void
    {
        require_once base_path('packages/gov-store/geo-areas/src/database/migrations/2024_01_01_000000_create_gov_geo_areas_table.php');
        $migration = new \CreateGovGeoAreasTable;
        $migration->up();

        $this->assertSame('Chattogram', GeoArea::find(1)->en_name);
        $this->assertSame(4, GeoArea::count());
    }
}
