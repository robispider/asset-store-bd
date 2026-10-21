<?php

namespace Tests\Feature\GovStore;

use GovStore\GeoAreas\Models\GeoArea;
use GovStore\GeoAreas\Services\GeoAreaDatasetSync;
use GovStore\GeoAreas\Services\GeoAreaService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use RuntimeException;

/** BBS geocode adoption: docs/plans/bbs-geocode-adoption-plan.md §5–6. */
class GeoAreaDatasetSyncTest extends TestCase
{
    private const MIGRATIONS = __DIR__.'/../../../packages/gov-store/geo-areas/src/database/migrations/';

    private ?string $fixture = null;

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
        // The real package migrations: table + bundled CSV import, then the code columns.
        require_once self::MIGRATIONS.'2024_01_01_000000_create_gov_geo_areas_table.php';
        (new \CreateGovGeoAreasTable)->up();
        (require self::MIGRATIONS.'2026_10_21_000001_add_bbs_codes_to_gov_geo_areas.php')->up();
    }

    protected function tearDown(): void
    {
        if ($this->fixture) {
            File::deleteDirectory($this->fixture);
        }
        parent::tearDown();
    }

    public function test_fresh_install_only_needs_codes_and_the_row_the_old_importer_skips(): void
    {
        $report = app(GeoAreaDatasetSync::class)->run(withLocalities: false);

        // The 2024 importer skips GeoAreaId 1: the CSV's first field starts with a BOM.
        $this->assertSame(1, $report['admin_inserted']);
        $this->assertSame(0, $report['english_fixed']);
        $this->assertSame(0, $report['rows_without_code']);
        $this->assertSame(9197, DB::table('gov_geo_areas')->count());
    }

    public function test_existing_install_gets_codes_new_areas_and_fixes_without_touching_identifiers(): void
    {
        $this->loadPreBbsDataset();
        $before = DB::table('gov_geo_areas')->get()->keyBy('GeoAreaId');

        $report = app(GeoAreaDatasetSync::class)->run(withLocalities: false);

        $this->assertSame(0, $report['rows_without_code']);
        $this->assertSame(0, DB::table('gov_geo_areas')->whereNull('bbs_code')->count());
        // 86 BBS areas missing from GovStore, plus GeoAreaId 1 (see above).
        $this->assertSame(87, $report['admin_inserted']);
        $this->assertNull($before->get(1));
        $this->assertSame('20', GeoArea::find(1)->bbs_code);
        $this->assertSame(173, $report['english_fixed']);
        $this->assertSame(0, $report['bangla_filled']);

        foreach (DB::table('gov_geo_areas')->whereIn('GeoAreaId', $before->keys())->get() as $row) {
            $old = $before->get($row->GeoAreaId);
            foreach (['hid', 'geo_type', 'parent_geo_code', 'geo_code', 'bn_name', 'GeoLevel'] as $column) {
                $this->assertSame((string) $old->{$column}, (string) $row->{$column}, "{$column} of {$row->GeoAreaId}");
            }
            if ($old->en_name !== $row->en_name) {
                $this->assertTrue(GeoAreaDatasetSync::isJunkEnglish($old->en_name), "unexpected rename of {$row->GeoAreaId}");
            }
        }

        $again = app(GeoAreaDatasetSync::class)->run(withLocalities: false);
        $this->assertSame([0, 0, 0, 0], [$again['admin_inserted'], $again['codes_written'], $again['english_fixed'], $again['bangla_filled']]);
        $this->assertSame('bd-admin-areas-2026-10-21.1', app(GeoAreaDatasetSync::class)->installedVersion());
    }

    public function test_bundled_codes_follow_the_reviewed_decisions(): void
    {
        app(GeoAreaDatasetSync::class)->run(withLocalities: false);

        // Matched union: official 2017 code nested under its upazila's code.
        $bagdha = GeoArea::where('geo_type', 'union')->where('bbs_code', '10-06-_-02-_-142')->firstOrFail();
        $upazila = GeoArea::where('geo_code', $bagdha->parent_geo_code)->firstOrFail();
        $this->assertSame('bbs_2017', $bagdha->code_status);
        $this->assertStringStartsWith($upazila->bbs_code.'-', $bagdha->bbs_code);

        // Newer area (not in the 2017 volumes): kept, provisional code under its parent.
        $dasar = GeoArea::where('en_name', 'Dasar Upazilla')->firstOrFail();
        $this->assertTrue($dasar->isProvisional());
        $this->assertMatchesRegularExpression('/^30-54-_-P\d{2}$/', $dasar->bbs_code);

        // Orphan wards re-attached by adding their missing parent (geo_code 58845).
        $pirganj = GeoArea::where('geo_code', 58845)->firstOrFail();
        $this->assertSame('PIRGANJ POURASABHA', $pirganj->en_name);
        $this->assertSame(9, GeoArea::where('parent_geo_code', 58845)->where('geo_type', 'pouro_ward')->count());

        // Duplicates share the original's code and say so.
        $duplicate = GeoArea::find(5480);
        $this->assertSame('duplicate', $duplicate->code_status);
        $this->assertSame(GeoArea::find(5481)->bbs_code, $duplicate->bbs_code);
        $this->assertSame(5481, $duplicate->bbs_attributes['duplicate_of']);

        $this->assertSame($bagdha->GeoAreaId, app(GeoAreaService::class)->getByBbsCode('10-06-_-02-_-142')->GeoAreaId);
    }

    public function test_localities_load_and_typeahead_only_returns_them_when_asked(): void
    {
        $sync = new GeoAreaDatasetSync($this->fixtureBundle());
        $report = $sync->run();

        $this->assertSame(2, $report['localities_inserted']);
        $this->assertSame(0, $report['rows_without_code']);
        $mauza = GeoArea::find(1000000);
        $this->assertSame('mauza', $mauza->geo_type);
        $this->assertSame('/36/28/29/32/1000000/', $mauza->hid);
        $this->assertSame('47', $mauza->bbs_attributes['jl_no']);

        $service = app(GeoAreaService::class);
        $this->assertCount(0, $service->search('Fixture'));
        $this->assertCount(2, $service->search('Fixture', ['mauza', 'village']));
        $this->assertCount(2, $service->search('Fixture', ['mauza', 'village'], '/36/28/29/32/'));

        $this->assertSame(0, $sync->run()['localities_inserted']);
    }

    public function test_a_seed_file_that_does_not_match_the_manifest_is_refused(): void
    {
        $path = $this->fixtureBundle();
        file_put_contents($path.'/seed/geo_area_codes.csv', "\n", FILE_APPEND);

        $this->expectException(RuntimeException::class);
        (new GeoAreaDatasetSync($path))->run();
    }

    public function test_sync_command_dry_run_writes_nothing(): void
    {
        $exit = Artisan::call('govstore:geo-areas-sync', ['--dry-run' => true, '--without-localities' => true]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dry run', Artisan::output());
        $this->assertSame(0, DB::table('gov_geo_areas')->whereNotNull('bbs_code')->count());
    }

    /** The table as an install made before the BBS seed has it (old importer, base CSV). */
    private function loadPreBbsDataset(): void
    {
        DB::table('gov_geo_areas')->delete();
        $handle = fopen(__DIR__.'/../../../packages/gov-store/geo-areas/src/database/data/geo_areas.base.csv', 'r');
        $rows = [];
        while (($row = fgetcsv($handle, 1000, ',', '"', '')) !== false) {
            if (count($row) < 9 || ! is_numeric($row[0])) {
                continue; // as the 2024 importer: the BOM-prefixed first row is skipped
            }
            $rows[] = ['GeoAreaId' => (int) $row[0], 'hid' => trim($row[1]) ?: null, 'geo_type' => trim($row[2]),
                'parent_geo_code' => $row[3] !== '' ? (int) $row[3] : null, 'geo_code' => (int) $row[4],
                'bn_name' => trim($row[5]), 'domain' => trim($row[6]) ?: null, 'en_name' => trim($row[7]), 'GeoLevel' => (int) $row[8]];
        }
        fclose($handle);
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('gov_geo_areas')->insert($chunk);
        }
        $this->assertSame(9110, DB::table('gov_geo_areas')->count());
    }

    /** A copy of the bundled admin seed plus two hand-made localities under Subil union. */
    private function fixtureBundle(): string
    {
        $data = __DIR__.'/../../../packages/gov-store/geo-areas/src/database/data';
        $this->fixture = sys_get_temp_dir().'/geo-seed-'.bin2hex(random_bytes(4));
        File::ensureDirectoryExists($this->fixture.'/seed');
        File::copy($data.'/geo_areas.csv', $this->fixture.'/geo_areas.csv');
        File::copy($data.'/seed/geo_area_codes.csv', $this->fixture.'/seed/geo_area_codes.csv');
        $subil = collect(file($data.'/geo_areas.csv'))->first(fn ($l) => str_starts_with($l, '4,'));
        $this->assertNotNull($subil);
        $csv = "GeoAreaId,hid,geo_type,parent_geo_code,geo_code,bn_name,domain,en_name,GeoLevel,bbs_code,bbs_code_legacy,code_status,code_source,bbs_attributes\n"
            .'1000000,/36/28/29/32/1000000/,mauza,32,1000000,ফিক্সচার মৌজা,,Fixture Mauza,6,20-19-_-40-_-890-001,,bbs_2017,bbs-geocode-2017,"{""jl_no"":""47""}"'."\n"
            .'1000001,/36/28/29/32/1000000/1000001/,village,1000000,1000001,ফিক্সচার গ্রাম,,Fixture Village,7,20-19-_-40-_-890-001-010,,bbs_2017,bbs-geocode-2017,{}'."\n";
        file_put_contents($this->fixture.'/seed/geo_area_localities.csv.gz', gzencode($csv));
        $crlf = fn ($p) => hash('sha256', str_replace("\n", "\r\n", str_replace("\r\n", "\n", file_get_contents($p))));
        file_put_contents($this->fixture.'/seed/manifest.json', json_encode(['version' => 'fixture', 'sha256' => [
            'geo_areas.csv' => $crlf($this->fixture.'/geo_areas.csv'),
            'seed/geo_area_codes.csv' => hash_file('sha256', $this->fixture.'/seed/geo_area_codes.csv'),
            'seed/geo_area_localities.csv.gz' => hash_file('sha256', $this->fixture.'/seed/geo_area_localities.csv.gz'),
        ]]));

        return $this->fixture;
    }
}
