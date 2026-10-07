<?php

namespace Tests\Feature\GovStore;

use App\Models\Accessory;
use App\Models\Asset;
use App\Models\Category;
use App\Models\CompanyableScope;
use App\Models\Component;
use App\Models\Consumable;
use App\Models\License;
use App\Models\User;
use GovStore\Classification\Jobs\ExecuteStarterTemplateJob;
use GovStore\Classification\Services\BulkAdoptionService;
use GovStore\TenantScope\Builders\OfficeInventoryBuilder;
use GovStore\TenantScope\Concerns\UsesOfficeInventoryBuilder;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Exceptions\TenantBoundaryException;
use GovStore\TenantScope\Http\Controllers\TenantScopeController;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\InjectTenantScopeUi;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\TenantScope\Policies\AssetBoundaryPolicy;
use GovStore\TenantScope\Services\EffectivePermissionSet;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\TenantScope\Services\NationalChangeReview;
use GovStore\TenantScope\Services\SchemaKnowledge;
use GovStore\TenantScope\Services\TenantBoundaryService;
use GovStore\TenantScope\Services\TenantExecution;
use GovStore\TenantScope\Services\TenantWorkerLifecycle;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** All fixtures and mutations are isolated in SQLite memory. */
class TenantScopeTest extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'LOG_CHANNEL' => 'stderr', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'govstore-access.mode' => 'enforce']);
        DB::purge('sqlite');
        app(SchemaKnowledge::class)->clear();
        foreach (['assets', 'consumables', 'accessories', 'components', 'licenses'] as $name) {
            Schema::create($name, function (Blueprint $table) use ($name) {
                $table->increments('id');
                $table->string('name')->nullable();
                $table->integer('company_id')->nullable();
                if ($name !== 'licenses') {
                    $table->integer('location_id')->nullable();
                }
                $table->integer('qty')->default(5);
                $table->timestamp('deleted_at')->nullable();
                $table->timestamps();
            });
            foreach ([[1, 10, 20], [2, 11, 20], [3, 30, 40], [4, 31, 40]] as [$id, $office, $company]) {
                $row = ['id' => $id, 'company_id' => $company, 'name' => 'Stock '.$id];
                if ($name !== 'licenses') {
                    $row['location_id'] = $office;
                }
                DB::table($name)->insert($row);
            }
        }
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('company_id');
            $table->timestamp('deleted_at')->nullable();
        });
        DB::table('locations')->insert([['id' => 10, 'company_id' => 20], ['id' => 11, 'company_id' => 20], ['id' => 30, 'company_id' => 40], ['id' => 31, 'company_id' => 40]]);
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->nullable();
        });
        DB::table('companies')->insert([['id' => 20], ['id' => 40]]);
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id')->nullable();
            $table->integer('company_id')->nullable();
            $table->boolean('activated')->default(true);
            $table->text('permissions')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        DB::table('users')->insert(['id' => 1, 'location_id' => 10, 'company_id' => 20, 'permissions' => '{}']);
        Schema::create('permission_groups', function (Blueprint $table) {
            $table->increments('id');
            $table->text('permissions')->nullable();
        });
        Schema::create('users_groups', function (Blueprint $table) {
            $table->integer('user_id');
            $table->integer('group_id');
        });
        foreach (['gov_company_admins' => ['user_id', 'company_id'], 'gov_ict_jurisdictions' => ['user_id', 'geo_area_id'],
            'gov_location_profiles' => ['location_id', 'office_admin_id', 'geo_area_id'], 'gov_office_responsibilities' => ['user_id', 'location_id']] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->increments('id');
                foreach ($columns as $column) {
                    $table->integer($column)->nullable();
                }
                $table->string('role_slug')->nullable();
                $table->timestamps();
            });
        }
        Schema::create('gov_geo_areas', function (Blueprint $table) {
            $table->integer('GeoAreaId')->primary();
            $table->string('hid');
        });
        Schema::create('gov_office_memberships', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('location_id');
            $table->string('status');
            $table->boolean('is_home_office')->default(false);
            $table->timestamps();
        });
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        $context = app(TenantContext::class);
        $context->reset();
        $context->isActive = true;
        $context->locationId = 10;
        $context->companyId = 20;
        $context->allowedLocationIds = [10];
        $context->allowedCompanyIds = [20];
    }

    private function actor(bool $superuser = false, bool $nativeAdmin = false): User
    {
        $actor = Mockery::mock(User::class)->makePartial();
        $actor->id = 1;
        $actor->location_id = 10;
        $actor->company_id = 20;
        $actor->shouldReceive('isSuperUser')->andReturn($superuser);
        $actor->shouldReceive('hasAccess')->andReturn($nativeAdmin);
        auth()->setUser($actor);

        return $actor;
    }

    private function initialize(): void
    {
        app(InitializeTenantContext::class)->handle(Request::create('/'), fn () => response('ok'));
    }

    private function stock(string $class = Consumable::class)
    {
        // Retain the package scope; native FMCS has its own regression suite.
        return $class::withoutGlobalScope(CompanyableScope::class);
    }

    public function test_global_reads_and_selected_office_isolation_cover_all_inventory_models(): void
    {
        $this->actor(true);
        $this->initialize();
        foreach ([Asset::class, Consumable::class, Accessory::class, Component::class, License::class] as $class) {
            $this->assertInstanceOf(OfficeInventoryBuilder::class, $class::query());
            $this->assertSame([1, 2, 3, 4], $this->stock($class)->orderBy('id')->pluck('id')->all());
        }
        session()->put('gov_working_membership_id', 'ADMIN_MOCK_10');
        $this->initialize();
        foreach ([Asset::class, Consumable::class, Accessory::class, Component::class] as $class) {
            $this->assertSame([1], $this->stock($class)->pluck('id')->all());
        }
        session()->put('gov_working_membership_id', 'ADMIN_MOCK_999');
        $this->initialize();
        $this->assertSame([], $this->stock()->pluck('id')->all());
    }

    public function test_company_and_geography_reads_do_not_authorize_foreign_model_or_bulk_mutations(): void
    {
        $context = app(TenantContext::class);
        $context->allowedLocationIds = [10, 11];
        $context->isCompanyAdmin = true;
        $this->assertSame([1, 2], $this->stock()->orderBy('id')->pluck('id')->all());
        $foreign = $this->stock()->findOrFail(2);
        $policy = app(AssetBoundaryPolicy::class);
        $this->assertFalse($policy->canMutate($foreign, $context));
        $foreign->location_id = 10;
        $this->assertFalse($policy->canMutate($foreign, $context));
        $this->assertSame(0, $this->stock()->whereKey(2)->update(['qty' => 100]));
        $this->assertSame(0, $this->stock()->whereKey(2)->increment('qty'));
        $this->assertSame(0, $this->stock()->whereKey(2)->delete());
        $this->assertSame(0, $this->stock()->whereKey(2)->forceDelete());
        $this->assertSame(5, DB::table('consumables')->where('id', 2)->value('qty'));
        $this->assertSame(1, $this->stock()->whereKey(1)->update(['qty' => 8]));
        $context->allowedLocationIds = [10, 30];
        $context->allowedCompanyIds = null;
        $this->assertSame([1, 3], $this->stock()->orderBy('id')->pluck('id')->all());
        $this->assertSame([], $this->stock(License::class)->pluck('id')->all());
        $this->assertSame(0, $this->stock()->whereKey(3)->update(['qty' => 99]));
        $this->assertSame(5, DB::table('consumables')->where('id', 3)->value('qty'));
    }

    public function test_global_overview_refuses_inventory_writes_and_ownership_reassignment(): void
    {
        $context = app(TenantContext::class);
        $context->isGlobal = true;
        $context->locationId = null;
        foreach ([fn () => $this->stock()->update(['qty' => 99]), fn () => $this->stock()->delete(),
            fn () => app(TenantBoundaryService::class)->verify(new Consumable(['name' => 'Global creation']), 'create')] as $mutation) {
            try {
                $mutation();
                $this->fail('A global stock mutation was accepted.');
            } catch (TenantBoundaryException $e) {
                $this->assertSame(403, $e->getCode());
            }
        }
        $context->isGlobal = false;
        $context->locationId = 10;
        foreach ([fn () => $this->stock()->whereKey(1)->update(['location_id' => 30]),
            fn () => $this->stock()->whereKey(1)->increment('location_id'),
            fn () => $this->stock()->upsert([['id' => 3, 'qty' => 0]], ['id']),
            fn () => $this->stock()->insert(['name' => 'Foreign', 'company_id' => 40, 'location_id' => 30])] as $mutation) {
            try {
                $mutation();
                $this->fail('An ownership bypass was accepted.');
            } catch (TenantBoundaryException $e) {
                $this->assertSame(403, $e->getCode());
            }
        }
        $this->assertSame(20, DB::table('consumables')->sum('qty'));
    }

    public function test_bulk_or_predicates_cannot_escape_office_boundary_even_when_force_deleting(): void
    {
        $context = app(TenantContext::class);
        $context->allowedLocationIds = [10, 11];
        $this->assertSame(1, $this->stock()->whereKey(2)->orWhere('id', 1)->update(['qty' => 9]));
        $this->assertSame(5, DB::table('consumables')->where('id', 2)->value('qty'));
        $this->assertSame(0, $this->stock()->whereKey(3)->orWhere('id', 4)->forceDelete());
        $this->assertSame(4, DB::table('consumables')->count());
    }

    public function test_model_creation_remains_available_in_own_office_and_quiet_saves_cannot_create_foreign_stock(): void
    {
        $item = TenantScopedStockFixture::create(['name' => 'Own office stock', 'company_id' => 20, 'location_id' => 10]);
        $this->assertNotNull($item->id);
        $this->assertSame(5, DB::table('consumables')->count());
        $foreign = new TenantScopedStockFixture(['name' => 'Foreign stock', 'company_id' => 40, 'location_id' => 30]);
        try {
            $foreign->saveQuietly();
            $this->fail('Quiet save bypassed tenant ownership');
        } catch (TenantBoundaryException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->assertSame(5, DB::table('consumables')->count());
        $existingForeign = TenantScopedStockFixture::findOrFail(2);
        $existingForeign->location_id = 10;
        try {
            $existingForeign->saveQuietly();
            $this->fail('Quiet save rehomed a foreign row');
        } catch (TenantBoundaryException $e) {
            $this->assertSame(403, $e->getCode());
        }
        $this->assertSame(11, DB::table('consumables')->where('id', 2)->value('location_id'));
    }

    public function test_inactive_foreign_and_revoked_memberships_cannot_fall_back_to_native_office(): void
    {
        $this->actor();
        DB::table('gov_office_memberships')->insert([
            ['id' => 1, 'user_id' => 1, 'location_id' => 10, 'status' => 'active'],
            ['id' => 2, 'user_id' => 2, 'location_id' => 30, 'status' => 'active'],
            ['id' => 3, 'user_id' => 1, 'location_id' => 11, 'status' => 'inactive'],
        ]);
        foreach ([2, 3, 999, 'ADMIN_MOCK_30', '1.1', '1e0', 0, -1] as $selection) {
            session()->put('gov_working_membership_id', $selection);
            $this->initialize();
            $this->assertNull(app(TenantContext::class)->locationId);
            $this->assertSame([], $this->stock()->pluck('id')->all());
        }
        session()->put('gov_working_membership_id', 1);
        $this->initialize();
        $this->assertSame(10, app(TenantContext::class)->locationId);
        DB::table('gov_office_memberships')->where('id', 1)->update(['status' => 'inactive']);
        $this->initialize();
        $this->assertNull(app(TenantContext::class)->locationId);
        session()->forget('gov_working_membership_id');
        $this->initialize();
        $this->assertNull(app(TenantContext::class)->locationId);
    }

    public function test_native_admin_does_not_gain_company_scope_and_company_role_union_is_injected_once(): void
    {
        $actor = $this->actor(false, true);
        $this->initialize();
        $this->assertFalse(app(TenantContext::class)->isGlobal);
        $this->assertSame([10], app(TenantContext::class)->allowedLocationIds);
        DB::table('gov_company_admins')->insert(['user_id' => 1, 'company_id' => 20]);
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $this->initialize();
        $context = app(TenantContext::class);
        $this->assertSame([10, 11], $context->allowedLocationIds);
        $this->assertSame('storekeeper', $context->effectivePermissions->getRole());
        $this->assertTrue($context->effectivePermissions->has('assets.checkout'));
        $this->assertTrue($context->effectivePermissions->has('users.create'));
        $injected = json_decode($actor->getAttributes()['permissions'], true);
        $this->assertSame(1, $injected['assets.checkout']);
        $this->assertSame(1, $injected['users.create']);
        $this->assertArrayNotHasKey('admin', $injected);
        $this->assertArrayNotHasKey('superuser', $injected);
        DB::table('gov_company_admins')->delete();
        $this->initialize();
        $this->assertFalse($context->isCompanyAdmin);
        $this->assertSame([10], $context->allowedLocationIds);
        $this->assertFalse($context->effectivePermissions->has('users.create'));
    }

    public function test_ict_jurisdiction_uses_live_geography_across_companies_and_revokes_on_next_request(): void
    {
        $this->actor();
        DB::table('gov_geo_areas')->insert([['GeoAreaId' => 1, 'hid' => '01.'], ['GeoAreaId' => 2, 'hid' => '01.02.'], ['GeoAreaId' => 3, 'hid' => '09.']]);
        DB::table('gov_location_profiles')->insert([['location_id' => 10, 'geo_area_id' => 1], ['location_id' => 30, 'geo_area_id' => 2], ['location_id' => 31, 'geo_area_id' => 3]]);
        DB::table('gov_ict_jurisdictions')->insert(['user_id' => 1, 'geo_area_id' => 1]);
        $this->initialize();
        $this->assertSame([1, 3], $this->stock()->orderBy('id')->pluck('id')->all());
        DB::table('gov_ict_jurisdictions')->delete();
        $this->initialize();
        $this->assertSame([1], $this->stock()->pluck('id')->all());
    }

    public function test_schema_introspection_is_cached_but_context_and_grants_are_fresh(): void
    {
        DB::enableQueryLog();
        $this->stock()->count();
        $cold = count(DB::getQueryLog());
        DB::flushQueryLog();
        for ($i = 0; $i < 5; $i++) {
            $this->stock()->count();
        }
        $this->assertSame(5, count(DB::getQueryLog()));
        $this->assertGreaterThan(1, $cold);
        $this->actor();
        DB::flushQueryLog();
        $this->initialize();
        $initializationQueries = count(DB::getQueryLog());
        $this->assertLessThanOrEqual(12, $initializationQueries);
        DB::table('gov_access_grants')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper', 'access_request_id' => 1, 'expires_at' => now()->addMinute()]);
        $this->initialize();
        $this->assertTrue(app(TenantContext::class)->hasPermission('assets.checkout'));
        DB::table('gov_access_grants')->update(['expires_at' => now()->subMinute()]);
        $this->initialize();
        $this->assertFalse(app(TenantContext::class)->hasPermission('assets.checkout'));
    }

    public function test_permission_union_is_idempotent_and_retains_role_attribution(): void
    {
        $set = new EffectivePermissionSet(['assets.view', 'assets.checkout'], 'storekeeper', 'inventory_operator');
        $overlay = new EffectivePermissionSet(['assets.view', 'users.create'], 'company_admin', 'company_operations');
        $set->merge($overlay)->merge($overlay);
        $this->assertSame(['assets.view', 'assets.checkout', 'users.create'], $set->getPermissions());
        $this->assertSame('storekeeper', $set->getRole());
        $this->assertSame('inventory_operator', $set->getProfile());
    }

    private function executionSpec(int $office = 10): array
    {
        return ['actor_id' => 1, 'scope_type' => 'location', 'scope_id' => $office, 'ability' => 'catalog.office.adopt'];
    }

    public function test_background_execution_revalidates_actor_office_membership_and_ability_and_restores_shared_context(): void
    {
        DB::table('gov_office_memberships')->insert(['user_id' => 1, 'location_id' => 10, 'status' => 'active']);
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $previousActor = $this->actor(true);
        $context = app(TenantContext::class);
        $context->configs = ['original' => (object) ['id' => 1]];
        $execution = app(TenantExecution::class);
        $this->assertSame('ran', $execution->run($this->executionSpec(), function () use ($context) {
            $this->assertSame($context, app(TenantContext::class));
            $this->assertFalse($context->isGlobal);
            $this->assertSame([10], $context->allowedLocationIds);
            $this->assertSame(1, auth()->id());

            return 'ran';
        }));
        $this->assertSame($previousActor, auth()->user());
        $this->assertArrayHasKey('original', $context->configs);
        foreach ([30, 999] as $office) {
            try {
                $execution->run($this->executionSpec($office), fn () => $this->fail('Foreign execution reached'));
            } catch (AuthorizationException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
        DB::table('gov_office_memberships')->update(['status' => 'inactive']);
        try {
            $execution->run($this->executionSpec(), fn () => $this->fail('Revoked membership executed'));
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        DB::table('gov_office_memberships')->update(['status' => 'active']);
        DB::table('gov_office_responsibilities')->delete();
        config(['govstore-access.mode' => 'shadow']);
        try {
            $execution->run($this->executionSpec(), fn () => $this->fail('Shadow granted a job ability'));
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertSame($previousActor, auth()->user());
    }

    public function test_worker_cleanup_and_job_contracts_prevent_leakage_after_success_and_failure(): void
    {
        $context = app(TenantContext::class);
        $this->actor(true);
        $context->isGlobal = true;
        $lifecycle = app(TenantWorkerLifecycle::class);
        $lifecycle->begin();
        $this->assertSame($context, app(TenantContext::class));
        $this->assertFalse($context->isGlobal);
        $this->assertSame([], $context->allowedLocationIds);
        $this->assertNull(auth()->user());
        try {
            app(TenantExecution::class)->globalMaintenance(function () use ($context) {
                $this->assertFalse($context->isActive);
                throw new \RuntimeException('Simulated job failure');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated job failure', $e->getMessage());
        }
        $lifecycle->finish();
        $this->assertFalse($context->isActive);
        $this->assertNull($context->locationId);
        $this->assertNull(auth()->user());
        $lifecycle->begin();
        $this->assertSame([], $this->stock()->pluck('id')->all());
        $lifecycle->finish();
        $job = new ExecuteStarterTemplateJob([], 'location', 10, 1);
        $this->assertSame($this->executionSpec(), $job->tenantExecution());
        foreach (glob(base_path('packages/gov-store/*/src/Jobs/*.php')) as $file) {
            $source = file_get_contents($file);
            $this->assertMatchesRegularExpression('/TenantScopedExecution|GlobalTenantMaintenance/', $source, $file);
        }
    }

    public function test_unregistered_govstore_commands_fail_closed_and_reset_stale_context(): void
    {
        $context = app(TenantContext::class);
        $context->isGlobal = true;
        $this->actor(true);
        try {
            app('events')->dispatch(new CommandStarting('govstore:unreviewed', new ArrayInput([]), new NullOutput));
            $this->fail('Unregistered maintenance was accepted');
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertFalse($context->isGlobal);
        $this->assertTrue($context->isActive);
        $this->assertNull(auth()->user());
    }

    public function test_bus_executes_scoped_adoption_in_live_context_and_refuses_revoked_actor(): void
    {
        DB::table('gov_office_memberships')->insert(['user_id' => 1, 'location_id' => 10, 'status' => 'active']);
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $service = Mockery::mock(BulkAdoptionService::class);
        $service->shouldReceive('execute')->once()->with([], 'location', 10, 1)->andReturnUsing(function () {
            $this->assertSame(10, app(TenantContext::class)->locationId);
            $this->assertSame(1, auth()->id());

            return [];
        });
        app()->instance(BulkAdoptionService::class, $service);
        Bus::dispatchSync(new ExecuteStarterTemplateJob([], 'location', 10, 1));
        $this->assertNull(app(TenantContext::class)->locationId);
        $this->assertNull(auth()->user());
        DB::table('users')->where('id', 1)->update(['activated' => false]);
        try {
            Bus::dispatchSync(new ExecuteStarterTemplateJob([], 'location', 10, 1));
            $this->fail('Inactive actor reached the adoption engine');
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        $this->assertNull(app(TenantContext::class)->locationId);
        $this->assertNull(auth()->user());
    }

    public function test_company_execution_rejects_ordinary_member_and_preserves_context_when_callback_fails(): void
    {
        $execution = app(TenantExecution::class);
        $spec = ['actor_id' => 1, 'scope_type' => 'company', 'scope_id' => 20, 'ability' => 'catalog.office.adopt'];
        try {
            $execution->run($spec, fn () => $this->fail('Ordinary employee got company execution'));
        } catch (AuthorizationException $e) {
            $this->assertNotEmpty($e->getMessage());
        }
        DB::table('gov_company_admins')->insert(['user_id' => 1, 'company_id' => 20]);
        $previous = $this->actor();
        $context = app(TenantContext::class);
        try {
            $execution->run($spec, function () use ($context) {
                $this->assertTrue($context->isCompanyAdmin);
                $this->assertNull($context->locationId);
                $this->assertSame([10, 11], $context->allowedLocationIds);
                throw new \RuntimeException('Scoped callback failed');
            });
        } catch (\RuntimeException $e) {
            $this->assertSame('Scoped callback failed', $e->getMessage());
        }
        $this->assertSame(10, $context->locationId);
        $this->assertSame($previous, auth()->user());
        foreach (['20invalid', 0, -1] as $id) {
            try {
                $execution->run(array_replace($spec, ['scope_id' => $id]), fn () => $this->fail('Invalid target was accepted'));
            } catch (AuthorizationException $e) {
                $this->assertNotEmpty($e->getMessage());
            }
        }
    }

    public function test_boundary_failures_preserve_status_and_safe_reference_even_on_native_routes(): void
    {
        $request = Request::create('/api/v1/consumables/2', 'PATCH');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $this->actor());
        $handler = app(ExceptionHandler::class);
        $response = $handler->render($request, new TenantBoundaryException('SQL secret /private/path', 'OWNERSHIP', 403));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertArrayHasKey('reference_id', $response->getData(true));
        $this->assertStringNotContainsString('SQL secret', $response->getContent());
        $this->assertStringNotContainsString('/private/path', $response->getContent());
    }

    public function test_menu_uses_one_fresh_role_snapshot_for_many_items_and_observes_revocation(): void
    {
        $actor = $this->actor();
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $registry = new MenuRegistry;
        for ($i = 0; $i < 30; $i++) {
            $registry->register(['id' => 'item'.$i, 'title' => 'Post', 'permission' => 'storeops.documents.post']);
        }
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertCount(30, $registry->tree());
        $this->assertLessThanOrEqual(5, count(DB::getQueryLog()));
        DB::table('gov_office_responsibilities')->delete();
        $this->assertSame([], $registry->tree());
        $this->assertFalse(app(GovAccess::class)->decide($actor, 'storeops.documents.post')->allowed);
    }

    public function test_tenant_administration_denies_native_admin_even_in_shadow_and_checks_submitted_ids(): void
    {
        $nativeAdmin = $this->actor(false, true);
        config(['govstore-access.mode' => 'shadow']);
        foreach (['tenant.scope.view', 'tenant.scope.manage'] as $ability) {
            $this->assertFalse(app(GovAccess::class)->decide($nativeAdmin, $ability)->allowed);
            $this->assertTrue(app(GovAccess::class)->enforces($ability));
        }
        $controller = app(TenantScopeController::class);
        try {
            $controller->config();
            $this->fail('Native admin got national tenant configuration');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->actor(true);
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamp('deleted_at')->nullable();
        });
        $request = Request::create('/gov-store/admin/scope/mappings/store', 'POST', [
            'reference_type' => 'category', 'reference_id' => 999, 'scope_type' => 'company', 'scope_id' => 20,
        ]);
        try {
            $controller->storeMapping($request);
            $this->fail('Missing submitted reference was accepted');
        } catch (ModelNotFoundException $e) {
            $this->assertSame([999], $e->getIds());
        }
    }

    public function test_national_tenant_review_freezes_input_detects_stale_configuration_and_rejects_replay(): void
    {
        foreach (['categories', 'models'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('deleted_at')->nullable();
            });
        }
        Schema::create('gov_tenant_scopes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('reference_type');
            $table->string('scope_strategy');
            $table->boolean('show_only_used')->default(false);
            $table->timestamps();
        });
        DB::table('gov_tenant_scopes')->insert(['reference_type' => 'models', 'scope_strategy' => 'company']);
        $actor = $this->actor(true);
        $actor->id = 1;
        $request = Request::create('/gov-store/admin/scope/save-strategy', 'POST', ['strategies' => ['models' => ['strategy' => 'location']]]);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $actor);
        $request->setLaravelSession(app('session')->driver('array'));
        $route = app('router')->getRoutes()->getByName('gov.scope.save-strategy');
        $request->setRouteResolver(fn () => $route);
        $reviewer = app(NationalChangeReview::class);
        $this->assertSame(409, $reviewer->handle($request, 'tenant.scope.manage')->getStatusCode());
        $token = array_key_first($request->session()->get('gov_national_reviews'));
        $request->merge(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Isolated tenant review', 'strategies' => ['models' => ['strategy' => 'global']]]);
        $this->assertNull($reviewer->handle($request, 'tenant.scope.manage'));
        $this->assertSame('location', $request->input('strategies.models.strategy'));
        $request->merge(['review_token' => $token]);
        try {
            $reviewer->handle($request, 'tenant.scope.manage');
            $this->fail('Review replay was accepted');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(422, $e->getStatusCode());
        }
        $request->request->remove('review_token');
        $reviewer->handle($request, 'tenant.scope.manage');
        $token = array_key_first($request->session()->get('gov_national_reviews'));
        DB::table('gov_tenant_scopes')->update(['scope_strategy' => 'global']);
        $request->merge(['review_token' => $token]);
        try {
            $reviewer->handle($request, 'tenant.scope.manage');
            $this->fail('Stale national config was accepted');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
    }

    public function test_missing_context_cannot_read_categories_adopted_only_by_another_office(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id');
            $table->timestamp('deleted_at')->nullable();
        });
        Schema::create('gov_tenant_scope_mappings', function (Blueprint $table) {
            $table->integer('reference_id');
            $table->string('reference_type');
            $table->integer('scope_id');
            $table->string('scope_type');
            $table->boolean('is_active');
        });
        DB::table('categories')->insert([['id' => 1], ['id' => 2]]);
        DB::table('gov_tenant_scope_mappings')->insert(['reference_id' => 2, 'reference_type' => 'category', 'scope_id' => 30, 'scope_type' => 'location', 'is_active' => true]);
        $context = app(TenantContext::class);
        $context->locationId = null;
        $context->companyId = null;
        $this->assertSame([1], Category::query()->pluck('id')->all());
        $context->locationId = 30;
        $context->companyId = 40;
        $this->assertSame([1, 2], Category::query()->orderBy('id')->pluck('id')->all());
    }

    public function test_menu_renders_permission_filtered_escaped_html_and_all_changed_templates_compile(): void
    {
        $this->actor();
        $registry = new MenuRegistry;
        $registry->register(['id' => 'root', 'title' => 'Office <safe>']);
        $registry->register(['id' => 'access', 'parent' => 'root', 'title' => 'My access', 'route' => 'gov.access.index']);
        $registry->register(['id' => 'blocked', 'parent' => 'root', 'title' => 'National', 'permission' => 'catalog.master.manage']);
        app()->instance(MenuRegistry::class, $registry);
        $html = view('govscope::hooks.sidebar')->render();
        $this->assertStringContainsString('Office &lt;safe&gt;', $html);
        $this->assertStringContainsString('menu-access', $html);
        $this->assertStringNotContainsString('menu-blocked', $html);
        $this->assertStringNotContainsString('<script', $html);
        $layout = file_get_contents(resource_path('views/layouts/default.blade.php'));
        $this->assertStringContainsString("@includeIf('govscope::hooks.sidebar')", $layout);
        $this->assertNotContains(InjectTenantScopeUi::class, app('router')->getMiddlewareGroups()['web']);
        $files = array_merge(glob(base_path('packages/gov-store/tenant-scope/src/resources/views/hooks/*.blade.php')), [resource_path('views/layouts/default.blade.php')]);
        foreach ($files as $file) {
            $temporary = tempnam(sys_get_temp_dir(), 'tenant_blade_');
            try {
                file_put_contents($temporary, app('blade.compiler')->compileString(file_get_contents($file)));
                $process = proc_open([PHP_BINARY, '-l', $temporary], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $file.': '.$output);
            } finally {
                unlink($temporary);
            }
        }
    }
}

/** Minimal persistence fixture isolates the shared builder from unrelated native schema. */
class TenantScopedStockFixture extends Model
{
    use UsesOfficeInventoryBuilder;

    protected $table = 'consumables';

    protected $guarded = [];
}
