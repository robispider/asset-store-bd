<?php

namespace Tests\Feature\GovStore;

use GovStore\Organization\Events\OfficeProvisioned;
use GovStore\Organization\Http\Controllers\ProvisioningController;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\Classification\Jobs\ExecuteStarterTemplateJob;
use GovStore\Classification\Listeners\ProvisionStarterCatalog;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Mockery;

class OrganizationPackageTest extends TestCase
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
    }

    public function test_historical_organization_migration_preserves_existing_tables_and_rows(): void
    {
        foreach (['gov_location_profiles', 'gov_ict_jurisdictions', 'gov_location_roles', 'gov_organization_activity_logs'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->string('marker')->nullable();
            });
            DB::table($name)->insert(['marker' => $name]);
        }

        require_once base_path('packages/gov-store/organization/src/database/migrations/2024_01_04_000000_create_gov_organization_tables.php');
        $migration = new \CreateGovOrganizationTables;
        $migration->up();

        foreach (['gov_location_profiles', 'gov_ict_jurisdictions', 'gov_location_roles', 'gov_organization_activity_logs'] as $name) {
            $this->assertSame($name, DB::table($name)->value('marker'));
        }
    }

    public function test_office_type_migration_adds_profile_field_without_rebuilding_profile_rows(): void
    {
        Schema::create('gov_location_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('location_id')->unique();
            $table->unsignedInteger('geo_area_id');
        });
        DB::table('gov_location_profiles')->insert(['location_id' => 160, 'geo_area_id' => 36]);

        $migration = require base_path('packages/gov-store/organization/src/database/migrations/2026_10_07_000001_add_office_type_and_import_legacy_responsibilities.php');
        $migration->up();

        $profile = DB::table('gov_location_profiles')->first();
        $this->assertSame('default', $profile->office_type);
        $this->assertSame(160, (int) $profile->location_id);
    }

    public function test_legacy_office_roles_are_copied_to_membership_owner_and_retained_as_archive(): void
    {
        Schema::create('users', fn (Blueprint $table) => $table->increments('id'));
        Schema::create('locations', fn (Blueprint $table) => $table->increments('id'));
        Schema::create('gov_office_memberships', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('user_id');
            $table->string('status');
            $table->date('valid_until')->nullable();
        });
        Schema::create('gov_location_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('location_id')->unique();
            $table->unsignedInteger('geo_area_id');
        });
        Schema::create('gov_location_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('location_id')->unique();
            $table->unsignedInteger('primary_approver_id')->nullable();
            $table->unsignedInteger('final_approver_id')->nullable();
            $table->unsignedInteger('storekeeper_id')->nullable();
        });
        Schema::create('gov_office_responsibilities', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('user_id');
            $table->string('role_slug');
            $table->timestamps();
            $table->unique(['location_id', 'user_id', 'role_slug']);
        });
        DB::table('users')->insert([['id' => 7], ['id' => 8], ['id' => 9]]);
        DB::table('locations')->insert(['id' => 160]);
        DB::table('gov_location_profiles')->insert(['location_id' => 160, 'geo_area_id' => 36]);
        DB::table('gov_location_roles')->insert(['location_id' => 160, 'primary_approver_id' => 7, 'storekeeper_id' => 8, 'final_approver_id' => 9]);
        DB::table('gov_office_memberships')->insert([
            ['location_id' => 160, 'user_id' => 7, 'status' => 'active'],
            ['location_id' => 160, 'user_id' => 8, 'status' => 'active'],
            ['location_id' => 160, 'user_id' => 9, 'status' => 'inactive'],
        ]);

        $migration = require base_path('packages/gov-store/organization/src/database/migrations/2026_10_07_000001_add_office_type_and_import_legacy_responsibilities.php');
        $migration->up();

        $this->assertSame(2, DB::table('gov_office_responsibilities')->count());
        $this->assertDatabaseHas('gov_office_responsibilities', ['location_id' => 160, 'user_id' => 7, 'role_slug' => 'primary_approver']);
        $this->assertDatabaseHas('gov_office_responsibilities', ['location_id' => 160, 'user_id' => 8, 'role_slug' => 'storekeeper']);
        $this->assertDatabaseMissing('gov_office_responsibilities', ['location_id' => 160, 'user_id' => 9, 'role_slug' => 'final_approver']);
        $this->assertSame(7, (int) DB::table('gov_location_roles')->value('primary_approver_id'));
    }

    public function test_provisioned_event_has_explicit_actor_and_office_type_contract(): void
    {
        $location = new \App\Models\Location;
        $event = new OfficeProvisioned($location, 27, 'hospital', 31);

        $this->assertSame($location, $event->location);
        $this->assertSame(27, $event->userId);
        $this->assertSame('hospital', $event->officeType);
        $this->assertSame(31, $event->catalogActorId);
    }

    public function test_starter_catalog_does_not_queue_without_a_company_scoped_office_admin(): void
    {
        Bus::fake();
        $location = new \App\Models\Location;
        $location->id = 160;
        $location->company_id = null;
        $event = new OfficeProvisioned($location, 27, 'hospital');

        app(ProvisionStarterCatalog::class)->handle($event);

        Bus::assertNotDispatched(ExecuteStarterTemplateJob::class);
    }

    public function test_organization_translation_keys_are_bilingual(): void
    {
        $english = require base_path('packages/gov-store/organization/src/resources/lang/en-US/orglabel.php');
        $bangla = require base_path('packages/gov-store/organization/src/resources/lang/bn-BD/orglabel.php');

        $this->assertSame([], array_diff_key($english, $bangla), 'Bangla translations are missing English keys.');
        $this->assertSame([], array_diff_key($bangla, $english), 'English translations are missing Bangla keys.');
    }

    public function test_registry_paginates_and_scopes_counts_and_company_filters_to_ict_geography(): void
    {
        $this->createRegistrySchema();
        DB::table('gov_geo_areas')->insert([
            ['GeoAreaId' => 1, 'hid' => '/36/', 'geo_type' => 'divison', 'geo_code' => 36, 'bn_name' => 'চট্টগ্রাম', 'en_name' => 'Chattogram', 'GeoLevel' => 1],
            ['GeoAreaId' => 2, 'hid' => '/36/28/', 'geo_type' => 'district', 'geo_code' => 28, 'bn_name' => 'কুমিল্লা', 'en_name' => 'Cumilla', 'GeoLevel' => 2],
            ['GeoAreaId' => 3, 'hid' => '/25/', 'geo_type' => 'divison', 'geo_code' => 25, 'bn_name' => 'ঢাকা', 'en_name' => 'Dhaka', 'GeoLevel' => 1],
            ['GeoAreaId' => 4, 'hid' => '/25/26/', 'geo_type' => 'district', 'geo_code' => 26, 'bn_name' => 'ঢাকা জেলা', 'en_name' => 'Dhaka District', 'GeoLevel' => 2],
        ]);
        DB::table('companies')->insert([['id' => 10, 'name' => 'Office A'], ['id' => 20, 'name' => 'Office B']]);
        DB::table('gov_ict_jurisdictions')->insert(['user_id' => 5, 'geo_area_id' => 1]);

        $locations = [];
        $profiles = [];
        foreach (range(1, 50) as $number) {
            $inside = $number <= 40;
            $locationId = 100 + $number;
            $locations[] = [
                'id' => $locationId,
                'name' => sprintf('Office %02d', $number),
                'company_id' => $inside ? 10 : 20,
                'deleted_at' => null,
            ];
            $profiles[] = [
                'location_id' => $locationId,
                'geo_area_id' => $inside ? 2 : 4,
                'office_admin_id' => null,
                'lifecycle_status' => 'provisioned',
            ];
        }
        DB::table('locations')->insert($locations);
        DB::table('gov_location_profiles')->insert($profiles);

        $actor = Mockery::mock(User::class)->makePartial();
        $actor->id = 5;
        $actor->shouldReceive('isSuperUser')->andReturn(false);
        $actor->shouldReceive('hasAccess')->with('admin')->andReturn(false);
        auth()->setUser($actor);

        Paginator::currentPageResolver(fn () => 2);
        $view = app(ProvisioningController::class)->index(Request::create('/gov-store/admin/organization?page=2'), app(GeoAreaService::class));
        $offices = $view->getData()['offices'];

        $this->assertSame(40, $offices->total());
        $this->assertSame(2, $offices->currentPage());
        $this->assertCount(15, $offices->items());
        $this->assertSame(40, $view->getData()['totalOfficesCount']);
        $this->assertSame([10], $view->getData()['companies']->pluck('id')->all());
        Paginator::currentPageResolver(fn () => 1);
    }

    private function createRegistrySchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('username')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('gov_geo_areas', function (Blueprint $table) {
            $table->unsignedInteger('GeoAreaId')->primary();
            $table->string('hid')->nullable();
            $table->string('geo_type');
            $table->integer('geo_code');
            $table->string('bn_name');
            $table->string('en_name');
            $table->integer('GeoLevel');
        });
        Schema::create('gov_location_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('location_id')->unique();
            $table->unsignedInteger('geo_area_id');
            $table->unsignedInteger('office_admin_id')->nullable();
            $table->string('lifecycle_status');
        });
        Schema::create('gov_office_responsibilities', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('location_id');
            $table->unsignedInteger('user_id');
            $table->string('role_slug');
        });
        Schema::create('gov_ict_jurisdictions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('geo_area_id');
        });
    }
}
