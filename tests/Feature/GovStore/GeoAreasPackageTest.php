<?php

namespace Tests\Feature\GovStore;

use GovStore\GeoAreas\Http\Controllers\GeoAreaController;
use GovStore\GeoAreas\Models\GeoArea;
use GovStore\GeoAreas\Services\BoundarySet;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\GeoAreas\Services\GeoTree;
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

    /** Country, a city corporation under the district and a paurashava under the upazila (no hid, like the dataset). */
    private function seedTree(): void
    {
        DB::table('gov_geo_areas')->where('GeoAreaId', 1)->update(['parent_geo_code' => 10000001]);
        DB::table('gov_geo_areas')->where('GeoAreaId', 2)->update(['parent_geo_code' => 36]);
        DB::table('gov_geo_areas')->where('GeoAreaId', 3)->update(['parent_geo_code' => 28]);
        DB::table('gov_geo_areas')->where('GeoAreaId', 4)->update(['parent_geo_code' => 29]);
        DB::table('gov_geo_areas')->insert([
            ['GeoAreaId' => 9358, 'hid' => null, 'geo_type' => 'country', 'parent_geo_code' => 0, 'geo_code' => 10000001, 'bn_name' => 'বাংলাদেশ', 'en_name' => 'Bangladesh', 'GeoLevel' => 0],
            ['GeoAreaId' => 3142, 'hid' => '/68/', 'geo_type' => 'divison', 'parent_geo_code' => 10000001, 'geo_code' => 68, 'bn_name' => 'ঢাকা', 'en_name' => 'Dhaka', 'GeoLevel' => 1],
            ['GeoAreaId' => 5378, 'hid' => null, 'geo_type' => 'city', 'parent_geo_code' => 28, 'geo_code' => 58503, 'bn_name' => 'কুমিল্লা সিটি কর্পোরেশন', 'en_name' => 'CUMILLA CITY CORPORATION', 'GeoLevel' => 3],
            ['GeoAreaId' => 5600, 'hid' => null, 'geo_type' => 'pourasabha', 'parent_geo_code' => 29, 'geo_code' => 58700, 'bn_name' => 'দেবিদ্বার পৌরসভা', 'en_name' => 'DEBIDWAR POURASABHA', 'GeoLevel' => 4],
        ]);
        app(GeoTree::class)->forget();
    }

    public function test_children_follow_the_tree_from_the_country_down_with_locale_and_short_names(): void
    {
        $this->seedTree();
        $service = app(GeoAreaService::class);

        $divisions = $service->children(9358, 'en-US');
        $this->assertSame([1, 3142], array_column($divisions, 'id'));
        $this->assertSame('division', $divisions[0]['type']);
        $this->assertTrue($divisions[0]['has_children']);
        $this->assertFalse($divisions[1]['has_children']);

        $districtKids = $service->children(2, 'en-US');
        $this->assertSame(['Cumilla City Corporation', 'Debidwar Upazila'], array_column($districtKids, 'name'));
        $this->assertTrue($districtKids[0]['urban']);
        $this->assertSame('Cumilla', app(GeoTree::class)->name(2, 'en-US', true));
        $this->assertSame('কুমিল্লা', app(GeoTree::class)->name(2, 'bn-BD', true));
        $this->assertSame('কুমিল্লা জেলা', $service->children(1, 'bn-BD')[0]['name']);
    }

    public function test_urban_units_roll_up_and_common_ancestor_spans_the_offices(): void
    {
        $this->seedTree();
        $service = app(GeoAreaService::class);
        $tree = app(GeoTree::class);

        $this->assertSame(2, $service->rollupArea(5378));     // city corporation → its district
        $this->assertSame(3, $service->rollupArea(5600));     // paurashava → its upazila
        $this->assertSame(4, $service->rollupArea(4));        // an ordinary area is its own roll-up
        $this->assertNull($service->rollupArea(999999));
        $this->assertSame([9358, 1, 2, 5378], $tree->chain(5378));
        $this->assertSame(2, $tree->commonAncestor([5378, 4]));
        $this->assertSame(9358, $tree->commonAncestor([4, 3142]));
        $this->assertSame(9358, $tree->commonAncestor([]));
    }

    public function test_search_can_return_parent_and_trail_for_the_map_find_box(): void
    {
        $this->seedTree();
        app()->setLocale('en-US');
        $rows = app(GeoAreaController::class)->search(Request::create('/', 'GET', ['q' => 'Debidwar', 'with_parent' => 1]), app(GeoAreaService::class))->getData(true);
        $upazila = collect($rows)->firstWhere('id', 3);
        $this->assertSame(2, $upazila['parent_id']);
        $this->assertSame(3, $upazila['level']);
        $this->assertSame(['Chattogram', 'Cumilla'], $upazila['trail']);
        $plain = app(GeoAreaController::class)->search(Request::create('/', 'GET', ['q' => 'Debidwar']), app(GeoAreaService::class))->getData(true);
        $this->assertArrayNotHasKey('parent_id', $plain[0]);
    }

    public function test_children_endpoint_returns_trail_and_404_for_unknown_areas(): void
    {
        $this->seedTree();
        app()->setLocale('en-US');
        $response = app(GeoAreaController::class)->children(1, app(GeoAreaService::class), app(GeoTree::class));
        $data = $response->getData(true);
        $this->assertSame(['Bangladesh', 'Chattogram'], array_column($data['trail'], 'name'));
        $this->assertSame([2], array_column($data['children'], 'id'));
        $this->assertSame('/gov-store/api/geo/areas/1/children', parse_url(route('gov.geo.children', 1), PHP_URL_PATH));

        $this->expectException(\Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
        app(GeoAreaController::class)->children(424242, app(GeoAreaService::class), app(GeoTree::class));
    }

    public function test_every_shape_in_the_boundary_set_is_a_govstore_area_with_label_points(): void
    {
        $set = app(BoundarySet::class);
        $this->assertTrue($set->exists(), 'configured boundary set is missing');
        $ids = [];
        $handle = fopen(base_path('packages/gov-store/geo-areas/src/database/data/geo_areas.csv'), 'r');
        while (($row = fgetcsv($handle)) !== false) {
            $ids[(int) preg_replace('/\D/', '', $row[0])] = true;
        }
        fclose($handle);
        $index = json_decode(file_get_contents($set->sourcePath().'/index.json'), true);
        foreach ($index['shapes'] as $level => $shapeIds) {
            $this->assertNotEmpty($shapeIds, "level {$level} has no shapes");
            $this->assertSame([], array_values(array_filter($shapeIds, fn ($id) => ! isset($ids[$id]))), "level {$level} shape ids not in the dataset");
        }
        $this->assertTrue($set->hasChildrenFile(9358));
        $this->assertSame($set->baseUrl().'/children/9358.json', $set->childrenUrl(9358));
        $country = json_decode(file_get_contents($set->sourcePath().'/children/9358.json'), true);
        $this->assertCount(8, $country['areas']);
        foreach ($country['areas'] as $area) {
            [$x, $y, $w, $h] = $area['bbox'];
            $this->assertTrue($area['label'][0] >= $x && $area['label'][0] <= $x + $w && $area['label'][1] >= $y && $area['label'][1] <= $y + $h);
        }
        $flat = json_decode(file_get_contents($set->sourcePath().'/flat/level-2.json'), true);
        $this->assertCount(64, $flat['areas']);
    }

    public function test_publish_copies_the_set_and_verifies_sha256_against_the_manifest(): void
    {
        $target = sys_get_temp_dir().'/geo-publish-'.uniqid();
        $this->artisan('geo-areas:publish-boundaries', ['--target' => $target])->assertExitCode(0);
        $version = config('geo-areas.boundary_version');
        $manifest = json_decode(file_get_contents($target.'/'.$version.'/manifest.json'), true);
        $this->assertSame($manifest['files']['children/9358.json']['sha256'], hash_file('sha256', $target.'/'.$version.'/children/9358.json'));
        $this->artisan('geo-areas:publish-boundaries', ['--target' => $target, '--verify' => true])->assertExitCode(0);

        file_put_contents($target.'/'.$version.'/children/9358.json', '{}');
        $this->artisan('geo-areas:publish-boundaries', ['--target' => $target, '--verify' => true])->assertExitCode(1);
        \Illuminate\Support\Facades\File::deleteDirectory($target);
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
