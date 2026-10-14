<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use GovStore\GeoAreas\Services\GeoAreaService;
use GovStore\Organization\Http\Controllers\ProvisioningController;
use GovStore\Organization\Models\LocationProfile;
use GovStore\Organization\ViewModels\OfficeRegistryViewModel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Mockery;

/** The human-centred Organization redesign (docs/plans/organization-ux-redesign-plan.md). SQLite memory only. */
class OrganizationUxTest extends TestCase
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
        $this->schema();
        $actor = Mockery::mock(User::class)->makePartial();
        $actor->id = 1;
        $actor->shouldReceive('isSuperUser')->andReturn(true);
        $actor->shouldReceive('hasAccess')->andReturn(true);
        auth()->setUser($actor);
    }

    protected function tearDown(): void
    {
        app()->setLocale('en-US');
        parent::tearDown();
    }

    public function test_redesigned_office_strings_are_bilingual_and_never_ask_for_english_input(): void
    {
        $english = require base_path('packages/gov-store/organization/src/resources/lang/en-US/office.php');
        $bangla = require base_path('packages/gov-store/organization/src/resources/lang/bn-BD/office.php');

        $this->assertSame([], array_keys(array_diff_key($english, $bangla)), 'Bangla office strings are missing English keys.');
        $this->assertSame([], array_keys(array_diff_key($bangla, $english)), 'English office strings are missing Bangla keys.');
        foreach ($bangla as $key => $text) {
            $this->assertStringNotContainsString('CHANGE', $text, $key);
        }
    }

    public function test_view_model_names_the_next_setup_step_in_flow_order(): void
    {
        $location = fn (string $status, ?int $admin) => (object) ['id' => 1, 'name' => 'A', 'parent' => null, 'company' => null, 'state' => null,
            'profile' => (object) ['geoArea' => null, 'officeAdmin' => null, 'office_admin_id' => $admin, 'lifecycle_status' => $status]];
        $roles = fn ($primary, $store) => (object) ['primary_approver_id' => $primary, 'storekeeper_id' => $store];

        $this->assertSame('next_staff', (new OfficeRegistryViewModel($location('provisioned', null), $roles(null, null), 0))->nextStep);
        $this->assertSame('next_admin', (new OfficeRegistryViewModel($location('provisioned', null), $roles(null, null), 3))->nextStep);
        $this->assertSame('next_approver', (new OfficeRegistryViewModel($location('configured', 7), $roles(null, 9), 3))->nextStep);
        $storekeeper = new OfficeRegistryViewModel($location('configured', 7), $roles(8, null), 3);
        $this->assertSame('next_storekeeper', $storekeeper->nextStep);
        $this->assertSame(3, $storekeeper->done);
        $this->assertTrue($storekeeper->needsAction());
        $this->assertSame('next_paused', (new OfficeRegistryViewModel($location('suspended', 7), $roles(8, 9), 3))->nextStep);
        $unconfigured = (object) ['id' => 2, 'name' => 'B', 'parent' => null, 'company' => null, 'state' => null, 'profile' => null];
        $this->assertSame('next_place', (new OfficeRegistryViewModel($unconfigured))->nextStep);
    }

    public function test_registry_status_tabs_count_every_state_and_filter_unonboarded_locations(): void
    {
        DB::table('gov_geo_areas')->insert(['GeoAreaId' => 1, 'hid' => '/25/', 'geo_type' => 'divison', 'geo_code' => 25, 'bn_name' => 'ঢাকা', 'en_name' => 'Dhaka', 'GeoLevel' => 1]);
        foreach (['provisioned', 'provisioned', 'configured', 'operational', 'suspended'] as $i => $status) {
            DB::table('locations')->insert(['id' => $i + 1, 'name' => 'Office '.($i + 1)]);
            DB::table('gov_location_profiles')->insert(['location_id' => $i + 1, 'geo_area_id' => 1, 'lifecycle_status' => $status]);
        }
        DB::table('locations')->insert([['id' => 6, 'name' => 'Legacy 6'], ['id' => 7, 'name' => 'Legacy 7']]);

        $view = $this->registry([]);
        $this->assertSame(['all' => 7, 'action' => 5, 'setup' => 3, 'live' => 1, 'paused' => 1, 'none' => 2], $view->getData()['tabCounts']);
        $this->assertSame('all', $view->getData()['activeStatus']);

        foreach ([
            'none' => ['Legacy 6', 'Legacy 7'],
            'unconfigured' => ['Legacy 6', 'Legacy 7'],
            'paused' => ['Office 5'],
            'setup' => ['Office 1', 'Office 2', 'Office 3'],
            'action' => ['Legacy 6', 'Legacy 7', 'Office 1', 'Office 2', 'Office 3'],
        ] as $status => $names) {
            $this->assertSame($names, collect($this->registry(['status' => $status])->getData()['offices']->items())->pluck('name')->all(), $status);
        }
        $rows = collect($view->getData()['offices']->items());
        $this->assertSame('next_place', $rows->firstWhere('name', 'Legacy 6')->nextStep);
        $this->assertSame('next_paused', $rows->firstWhere('name', 'Office 5')->nextStep);
        $this->assertSame('next_staff', $rows->firstWhere('name', 'Office 1')->nextStep);
    }

    public function test_area_search_returns_each_result_with_its_parent_path_in_the_users_language(): void
    {
        DB::table('gov_geo_areas')->insert([
            ['GeoAreaId' => 1, 'hid' => '/30/', 'geo_type' => 'divison', 'geo_code' => 30, 'bn_name' => 'ঢাকা বিভাগ', 'en_name' => 'Dhaka Division', 'GeoLevel' => 1],
            ['GeoAreaId' => 2, 'hid' => '/30/26/', 'geo_type' => 'district', 'geo_code' => 26, 'bn_name' => 'ঢাকা জেলা', 'en_name' => 'Dhaka District', 'GeoLevel' => 2],
            ['GeoAreaId' => 3, 'hid' => '/30/26/72/', 'geo_type' => 'upazilla', 'geo_code' => 72, 'bn_name' => 'সাভার', 'en_name' => 'Savar', 'GeoLevel' => 3],
        ]);

        $search = fn () => app(ProvisioningController::class)->geoSearch(Request::create('/geo-search', 'GET', ['q' => 'Savar']), app(GeoAreaService::class))->getData(true);
        app()->setLocale('en-US');
        $this->assertSame(['Dhaka Division', 'Dhaka District'], $search()[0]['path']);
        $this->assertSame('Savar', $search()[0]['text']);
        app()->setLocale('bn-BD');
        $this->assertSame(['ঢাকা বিভাগ', 'ঢাকা জেলা'], $search()[0]['path']);
        $this->assertSame('সাভার', $search()[0]['text']);
    }

    public function test_office_form_keeps_every_value_and_shows_field_errors_after_a_failed_save(): void
    {
        DB::table('gov_geo_areas')->insert(['GeoAreaId' => 3, 'hid' => '/30/26/72/', 'geo_type' => 'upazilla', 'geo_code' => 72, 'bn_name' => 'সাভার', 'en_name' => 'Savar', 'GeoLevel' => 3]);
        app()->setLocale('en-US');
        $this->app['request']->setLaravelSession($this->app['session.store']);
        $this->app['session.store']->put('_old_input', ['name' => 'Upazila Health Complex', 'office_type' => 'hospital', 'geo_area_id' => 3, 'company_id' => 20, 'parent_id' => 11]);
        view()->share('errors', (new ViewErrorBag)->put('default', new MessageBag(['geo_area_id' => ['Choose the area where this office is located.']])));

        $html = view('govorg::partials.office-form', [
            'mode' => 'create', 'scopeArea' => null, 'preselectedLocation' => null, 'unprovisionedLocations' => collect(),
            'companies' => collect([(object) ['id' => 10, 'name' => 'Ministry X'], (object) ['id' => 20, 'name' => 'Ministry Y']]),
            'offices' => collect([(object) ['id' => 11, 'name' => 'Parent office']]),
        ])->render();

        $this->assertStringContainsString('value="Upazila Health Complex"', $html);
        $this->assertMatchesRegularExpression('/value="hospital"[^>]*checked/', $html);
        $this->assertMatchesRegularExpression('/<option value="3" selected>Savar<\/option>/', $html);
        $this->assertMatchesRegularExpression('/<option value="20"\s+selected/', $html);
        $this->assertMatchesRegularExpression('/<option value="11"\s+selected/', $html);
        $this->assertStringContainsString('gs-form-row--error', $html);
        $this->assertStringContainsString('Choose the area where this office is located.', $html);
        $this->assertStringContainsString('href="#geo_area_id"', $html);
        $this->assertStringNotContainsString('office_admin_id', $html, 'The always-disabled administrator select is gone.');
    }

    public function test_staff_waiting_checklist_reflects_real_state_and_offers_no_admin_actions(): void
    {
        $profile = new LocationProfile(['lifecycle_status' => 'provisioned', 'office_admin_id' => null]);
        $profile->setRelation('officeAdmin', null);
        $readiness = ['checklist' => ['has_office_admin' => false, 'has_primary_approver' => true, 'has_storekeeper' => false, 'has_users' => true], 'users_count' => 3];

        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale);
            $html = view('govorg::partials.readiness', ['readiness' => $readiness, 'profile' => $profile, 'roles' => null, 'people' => [], 'readonly' => true])->render();
            $this->assertStringContainsString(e(trans_choice('organization_labels::office.readiness_left', 2, ['count' => 2])), $html, $locale);
            $this->assertSame(2, substr_count($html, 'gs-readiness__item--done'), $locale);
            $this->assertStringNotContainsString('<a ', $html, 'Staff pages never offer admin actions.');
        }
    }

    private function registry(array $query)
    {
        return app(ProvisioningController::class)->index(Request::create('/gov-store/admin/organization', 'GET', $query), app(GeoAreaService::class));
    }

    private function schema(): void
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
