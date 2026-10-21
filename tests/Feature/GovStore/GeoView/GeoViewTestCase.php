<?php

namespace Tests\Feature\GovStore\GeoView;

use GovStore\GeoAreas\Services\GeoTree;
use GovStore\GeoView\Registry\GeoSourceRegistry;
use GovStore\GeoView\Services\OfficeLocator;
use App\Models\User;
use Mockery;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;

/**
 * In-memory SQLite with the real administrative tree (geo_areas.csv, no localities) and the real boundary set,
 * so payloads are checked against the shapes the map draws.
 */
abstract class GeoViewTestCase extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'LOG_CHANNEL' => 'stderr', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require __DIR__.'/../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'logging.default' => 'null']);
        DB::purge('sqlite');
        Cache::flush();
        Schema::create('gov_geo_areas', function (Blueprint $table) {
            $table->unsignedInteger('GeoAreaId')->primary();
            $table->string('hid')->nullable();
            $table->string('geo_type', 30);
            $table->integer('parent_geo_code')->nullable();
            $table->integer('geo_code');
            $table->string('bn_name');
            $table->string('en_name');
            $table->integer('GeoLevel');
        });
        Schema::create('gov_location_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id');
            $table->unsignedInteger('geo_area_id')->nullable();
            $table->timestamp('geo_area_verified_at')->nullable();
        });
        $rows = [];
        $handle = fopen(base_path('packages/gov-store/geo-areas/src/database/data/geo_areas.csv'), 'r');
        while (($r = fgetcsv($handle)) !== false) {
            $id = (int) preg_replace('/\D/', '', $r[0]);
            if (! $id || ! is_numeric($r[8] ?? null)) {
                continue;
            }
            $rows[] = ['GeoAreaId' => $id, 'hid' => $r[1] ?: null, 'geo_type' => $r[2], 'parent_geo_code' => (int) $r[3], 'geo_code' => (int) $r[4], 'bn_name' => $r[5], 'en_name' => $r[7], 'GeoLevel' => (int) $r[8]];
        }
        fclose($handle);
        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('gov_geo_areas')->insert($chunk);
        }
        foreach (FixtureProgrammeSource::OFFICES as $office => $area) {
            DB::table('gov_location_profiles')->insert(['location_id' => $office, 'geo_area_id' => $area, 'geo_area_verified_at' => $office === 172 ? now() : null]);
        }
        app(GeoTree::class)->forget();
        app(OfficeLocator::class)->forget();

        Gate::define('geo.fixture.view', fn ($user) => (int) $user->getAuthIdentifier() !== 4);
        $registry = app(GeoSourceRegistry::class);
        $registry->register(FixtureProgrammeSource::class);
        $registry->register(FixtureAreaSource::class);
        app()->setLocale('en-US');
    }

    /** A Snipe-IT user double: the app's Gate::before asks isSuperUser(), the fixture ability decides the rest. */
    protected function actAs(int $id): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->shouldReceive('isSuperUser')->andReturn(false);
        $user->shouldReceive('hasAccess')->andReturn(false);
        $user->shouldReceive('getAuthIdentifier')->andReturn($id);
        auth()->setUser($user);

        return $user;
    }
}
