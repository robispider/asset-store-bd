<?php

namespace Tests\Feature\GovStore;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Location;
use App\Models\Manufacturer;
use App\Models\Supplier;
use App\Models\User;
use Carbon\Carbon;
use GovStore\Classification\Http\Controllers\MyCatalogController;
use GovStore\Classification\Http\Controllers\ReferenceProvisioningController;
use GovStore\Classification\Services\CatalogCategoryCreator;
use GovStore\Classification\Services\CategoryAdoptionService;
use GovStore\Classification\Services\ProductTypeFinanceDefault;
use GovStore\CustomRequests\Services\ApprovalRouting;
use GovStore\Metadata\Http\Controllers\MetadataHealthController;
use GovStore\Metadata\Jobs\ConvergeMetadataJob;
use GovStore\Metadata\Services\ConvergenceEngine;
use GovStore\OfficeMembership\Services\LegacyUserSynchronizationService;
use GovStore\OfficeMembership\Services\RoleAssignmentService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Exceptions\TenantBoundaryException;
use GovStore\TenantScope\Http\Controllers\AccessController;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\RequireGovAbility;
use GovStore\TenantScope\Policies\CategoryBoundaryPolicy;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\TenantScope\Services\ReferenceOwnershipService;
use GovStore\TenantScope\Services\SchemaKnowledge;
use GovStore\TenantScope\Services\TenantBoundaryService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Regression fixtures are exclusively SQLite memory; no development data is touched. */
class PermissionRemediationTest extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'LOG_CHANNEL' => 'null'] as $key => $value) {
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'govstore-access.mode' => 'enforce', 'logging.default' => 'null']);
        DB::purge('sqlite');
        app(SchemaKnowledge::class)->clear();
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('first_name')->default('Fixture');
            $t->string('last_name')->nullable();
            $t->string('username')->nullable();
            $t->integer('location_id');
            $t->integer('company_id');
            $t->boolean('activated')->default(true);
            $t->text('permissions')->default('{}');
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('permission_groups', function (Blueprint $t) {
            $t->increments('id');
            $t->text('permissions')->nullable();
        });
        Schema::create('users_groups', function (Blueprint $t) {
            $t->integer('user_id');
            $t->integer('group_id');
        });
        foreach (['companies', 'locations'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name) {
                $t->increments('id');
                $t->string('name')->default('Fixture');
                $t->timestamp('deleted_at')->nullable();
                if ($name === 'locations') {
                    $t->integer('company_id');
                    $t->integer('parent_id')->nullable();
                }
                $t->timestamps();
            });
        }
        foreach (['gov_company_admins' => ['user_id', 'company_id'], 'gov_ict_jurisdictions' => ['user_id', 'geo_area_id'],
            'gov_location_profiles' => ['location_id', 'office_admin_id', 'geo_area_id'], 'gov_office_responsibilities' => ['user_id', 'location_id']] as $name => $columns) {
            Schema::create($name, function (Blueprint $t) use ($columns) {
                $t->increments('id');
                foreach ($columns as $column) {
                    $t->integer($column)->nullable();
                } $t->string('role_slug')->nullable();
                $t->timestamps();
            });
        }
        Schema::create('gov_office_memberships', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('location_id');
            $t->string('status');
            $t->boolean('is_home_office')->default(false);
            $t->date('valid_until')->nullable();
            $t->timestamps();
        });
        foreach (['categories', 'models', 'suppliers', 'manufacturers'] as $name) {
            Schema::create($name, function (Blueprint $t) use ($name) {
                $t->increments('id');
                $t->string('name');
                $t->timestamp('deleted_at')->nullable();
                $t->timestamps();
                if ($name === 'categories') {
                    $t->string('category_type')->default('asset');
                }
                if ($name === 'models') {
                    $t->integer('category_id')->nullable();
                    $t->integer('fieldset_id')->nullable();
                    $t->integer('manufacturer_id')->nullable();
                    $t->string('model_number')->nullable();
                }
            });
        }
        Schema::create('assets', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('company_id');
            $t->integer('location_id');
            $t->integer('assigned_to')->nullable();
            $t->string('assigned_type')->nullable();
            $t->integer('status_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('action_logs', function (Blueprint $t) {
            $t->increments('id');
            foreach (['item_id', 'created_by', 'company_id'] as $column) {
                $t->integer($column)->nullable();
            }
            foreach (['item_type', 'action_type', 'remote_ip', 'user_agent', 'action_source'] as $column) {
                $t->string($column)->nullable();
            }
            $t->text('log_meta')->nullable();
            $t->timestamp('action_date')->nullable();
            $t->timestamps();
        });
        Schema::create('gov_organization_activity_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('location_id');
            $t->integer('performed_by');
            $t->string('event_type');
            $t->text('details');
            $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('gov_tenant_scope_mappings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('reference_type');
            $t->integer('reference_id');
            $t->string('scope_type');
            $t->integer('scope_id');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
        foreach (['2026_10_04_000001_create_gov_access_tables', '2026_10_26_000001_create_gov_reference_owners', '2026_10_26_000002_create_gov_reviewed_operations'] as $file) {
            (require base_path('packages/gov-store/tenant-scope/src/database/migrations/'.$file.'.php'))->up();
        }
        (require base_path('packages/gov-store/classification/src/database/migrations/2024_01_06_000000_create_gov_category_governance_table.php'))->up();
        (require base_path('packages/gov-store/classification/src/database/migrations/2024_04_01_000000_create_gov_catalog_tables.php'))->up();
        DB::table('companies')->insert([['id' => 20], ['id' => 40]]);
        DB::table('locations')->insert([['id' => 10, 'company_id' => 20], ['id' => 11, 'company_id' => 20], ['id' => 30, 'company_id' => 40]]);
        foreach ([[1, 10, 20], [2, 11, 20], [3, 11, 20], [4, 30, 40]] as [$id,$office,$company]) {
            DB::table('users')->insert(['id' => $id, 'location_id' => $office, 'company_id' => $company, 'username' => 'fixture'.$id]);
        }
        DB::table('gov_office_memberships')->insert(['user_id' => 1, 'location_id' => 10, 'status' => 'active', 'is_home_office' => true]);
        $context = app(TenantContext::class);
        $context->reset();
        $context->isActive = true;
        $context->locationId = 10;
        $context->companyId = 20;
        $context->allowedLocationIds = [10];
        $context->allowedCompanyIds = [20];
        $this->actor();
    }

    private function actor(): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->company_id = 20;
        $user->location_id = 10;
        $user->shouldReceive('isSuperUser')->andReturn(false);
        auth()->setUser($user);

        return $user;
    }

    private function denied(callable $call, int $status = 403): void
    {
        try {
            $call();
            $this->fail('Expected authorization failure.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    private function category(string $scope = 'national', ?int $owner = null): Category
    {
        $id = DB::table('categories')->insertGetId(['name' => 'Type '.DB::table('categories')->count(), 'category_type' => 'asset']);
        $category = Category::withoutGlobalScopes()->findOrFail($id);
        app(ReferenceOwnershipService::class)->assign($category, $scope, $owner, 1, 'Regression fixture ownership');

        return $category;
    }

    public function test_combined_duties_and_cover_reach_native_permissions_and_expire_next_request(): void
    {
        DB::table('gov_location_profiles')->insert(['location_id' => 10, 'office_admin_id' => 1]);
        DB::table('gov_access_grants')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper', 'expires_at' => now()->addHour(), 'access_request_id' => 1]);
        app(InitializeTenantContext::class)->handle(Request::create('/'), fn () => response('ok'));
        $this->assertTrue(auth()->user()->hasAccess('assets.checkout'));
        $this->assertTrue(app(GovAccess::class)->decide(auth()->user(), 'storeops.documents.post')->allowed);
        DB::table('gov_access_grants')->update(['expires_at' => now()->subSecond()]);
        app(InitializeTenantContext::class)->handle(Request::create('/'), fn () => response('ok'));
        $this->assertFalse(auth()->user()->hasAccess('assets.checkout'));
        $this->assertFalse(app(GovAccess::class)->decide(auth()->user(), 'storeops.documents.post')->allowed);
    }

    public function test_secondary_members_are_visible_only_while_active_and_in_the_company(): void
    {
        DB::table('gov_office_memberships')->insert([
            ['user_id' => 2, 'location_id' => 10, 'status' => 'active', 'valid_until' => today()->addDay()],
            ['user_id' => 3, 'location_id' => 10, 'status' => 'active', 'valid_until' => today()->subDay()],
            ['user_id' => 4, 'location_id' => 10, 'status' => 'active', 'valid_until' => null],
        ]);
        $this->assertNotNull(User::find(2));
        $this->assertNull(User::find(3));
        $this->assertNull(User::find(4));
    }

    public function test_cover_can_issue_to_secondary_member_without_moving_inventory_ownership(): void
    {
        DB::table('gov_office_memberships')->insert(['user_id' => 2, 'location_id' => 10, 'status' => 'active']);
        DB::table('gov_access_grants')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper', 'expires_at' => now()->addHour(), 'access_request_id' => 1]);
        DB::table('assets')->insert(['id' => 1, 'company_id' => 20, 'location_id' => 10]);
        $asset = Asset::withoutGlobalScopes()->findOrFail(1);
        $asset->assigned_to = 2;
        $asset->assigned_type = User::class;
        $asset->location_id = 11;
        app(TenantBoundaryService::class)->verify($asset, 'update');
        $this->assertSame(10, $asset->location_id);
        DB::table('gov_access_grants')->delete();
        $this->expectException(TenantBoundaryException::class);
        app(TenantBoundaryService::class)->verify($asset, 'update');
    }

    public function test_native_location_edit_never_creates_or_revives_membership(): void
    {
        DB::table('gov_office_memberships')->insert(['user_id' => 1, 'location_id' => 30, 'status' => 'rejected']);
        $user = User::withoutGlobalScopes()->findOrFail(1);
        $user->location_id = 30;
        app(LegacyUserSynchronizationService::class)->handleUpdatedUser($user);
        $this->assertSame(10, (int) $user->location_id);
        $this->assertSame('rejected', DB::table('gov_office_memberships')->where('location_id', 30)->value('status'));
        $this->assertSame('active', DB::table('gov_office_memberships')->where('location_id', 10)->value('status'));
    }

    public function test_adoption_and_abandonment_never_confer_national_ownership(): void
    {
        $category = $this->category();
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        app(CategoryAdoptionService::class)->useCategory($category->id, 'location', 10);
        $this->assertFalse(app(CategoryBoundaryPolicy::class)->canMutate($category, app(TenantContext::class)));
        app(CategoryAdoptionService::class)->archiveCategory($category->id, 'location', 10);
        DB::table('gov_tenant_scope_mappings')->delete();
        $this->assertSame('GLOBAL', app(ReferenceOwnershipService::class)->getOwnershipState($category));
        $category->name = 'Forbidden national rename';
        $this->expectException(TenantBoundaryException::class);
        $category->save();
    }

    public function test_office_model_key_and_company_management_authority_are_independent_of_adoption(): void
    {
        DB::table('models')->insert(['id' => 1, 'name' => 'Local model']);
        $model = AssetModel::withoutGlobalScopes()->findOrFail(1);
        app(ReferenceOwnershipService::class)->assign($model, 'office', 10, 1, 'Reviewed model owner');
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $this->assertTrue(app(CategoryBoundaryPolicy::class)->canMutate($model, app(TenantContext::class)));
        $category = $this->category('company', 20);
        $this->assertFalse(app(CategoryBoundaryPolicy::class)->canMutate($category, app(TenantContext::class)));
        DB::table('gov_company_admins')->insert(['user_id' => 1, 'company_id' => 20]);
        $this->assertTrue(app(CategoryBoundaryPolicy::class)->canMutate($category, app(TenantContext::class)));
    }

    public function test_foreign_and_missing_source_ids_cannot_be_adopted(): void
    {
        $category = $this->category('office', 30);
        foreach ([$category->id, 999] as $id) {
            try {
                app(CategoryAdoptionService::class)->useCategory($id, 'location', 10);
                $this->fail('Foreign source adopted.');
            } catch (ModelNotFoundException $e) {
                $this->assertSame(999, $id);
            } catch (HttpExceptionInterface $e) {
                $this->assertSame(404, $e->getStatusCode());
            }
        }
        $this->assertSame(0, DB::table('gov_tenant_scope_mappings')->count());
    }

    public function test_office_provisioning_cannot_remap_an_existing_national_type(): void
    {
        $category = $this->category();
        DB::table('gov_catalog_nodes')->insert(['code' => '12345678', 'title_en' => 'New commodity', 'level' => 4, 'hid' => '/12345678/', 'version' => 'fixture']);
        DB::table('gov_catalog_snipe_mappings')->insert(['code' => '87654321', 'category_id' => $category->id]);
        $this->denied(fn () => app(CatalogCategoryCreator::class)->provisionAndMap('12345678', 'asset', 'location', 'location', 10, 1, $category->name), 409);
        $this->assertSame('87654321', DB::table('gov_catalog_snipe_mappings')->value('code'));
    }

    public function test_native_reference_creates_are_refused_and_employee_profile_has_no_location_authority(): void
    {
        foreach ([Category::class, AssetModel::class, Supplier::class, Manufacturer::class, Location::class] as $class) {
            try {
                app(TenantBoundaryService::class)->verify(new $class, 'create');
                $this->fail('Native creation permitted.');
            } catch (TenantBoundaryException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
        $profiles = config('govstore-permissions.profiles');
        $this->assertNotContains('self.edit_location', $profiles['employee']);
        foreach ($profiles as $profile) {
            $this->assertNotContains('locations.create', $profile);
        }
    }

    public function test_employee_cannot_open_my_catalog_even_in_explicit_shadow(): void
    {
        config(['govstore-access.mode' => 'shadow']);
        $this->denied(fn () => app(MyCatalogController::class)->index(Request::create('/gov-store/operations/catalog'), app(TenantContext::class)));
    }

    public function test_narrow_catalog_and_reader_grants_do_not_grant_operational_duties(): void
    {
        foreach (['catalog_maintainer', 'document_reader'] as $role) {
            DB::table('gov_access_grants')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => $role, 'expires_at' => now()->addDay(), 'access_request_id' => 1]);
        }
        $access = app(GovAccess::class);
        $this->assertTrue($access->decide(auth()->user(), 'catalog.office.adopt')->allowed);
        $this->assertTrue($access->decide(auth()->user(), 'storeops.documents.view')->allowed);
        foreach (['storeops.documents.post', 'storeops.documents.draft', 'requests.approve.primary', 'requests.approve.final', 'requests.fulfill'] as $ability) {
            $this->assertFalse($access->decide(auth()->user(), $ability)->allowed, $ability);
        }
        $this->assertSame('catalog_maintainer', AccessController::REQUESTABLE['catalog.office.adopt']);
        $this->assertSame('document_reader', AccessController::REQUESTABLE['storeops.documents.view']);
        $this->assertArrayHasKey('requests.approve.final', AccessController::REQUESTABLE);
    }

    public function test_two_stage_path_excludes_self_inactive_and_expired_members_and_recognizes_cover(): void
    {
        DB::table('gov_office_responsibilities')->insert([
            ['user_id' => 1, 'location_id' => 10, 'role_slug' => 'primary_approver'], ['user_id' => 1, 'location_id' => 10, 'role_slug' => 'final_approver']]);
        $routing = app(ApprovalRouting::class);
        $this->assertFalse($routing->feasible(10, 'PRIMARY_AND_FINAL'));
        DB::table('gov_office_memberships')->insert(['user_id' => 2, 'location_id' => 10, 'status' => 'active', 'valid_until' => today()->addDay()]);
        DB::table('gov_access_grants')->insert(['user_id' => 2, 'location_id' => 10, 'role_slug' => 'final_approver', 'expires_at' => now()->addDay(), 'access_request_id' => 1]);
        $this->assertTrue($routing->feasible(10, 'PRIMARY_AND_FINAL'));
        $this->assertFalse($routing->feasible(10, 'PRIMARY_AND_FINAL', [1]));
        DB::table('users')->where('id', 2)->update(['activated' => false]);
        $this->assertFalse($routing->feasible(10, 'PRIMARY_AND_FINAL'));
        DB::table('users')->where('id', 2)->update(['activated' => true]);
        DB::table('gov_office_memberships')->where('user_id', 2)->update(['valid_until' => today()->subDay()]);
        $this->assertFalse($routing->feasible(10, 'PRIMARY_AND_FINAL'));
    }

    public function test_access_review_creates_expiring_narrow_grant_and_refuses_native_location_only_members(): void
    {
        DB::table('gov_location_profiles')->insert(['location_id' => 10, 'office_admin_id' => 1]);
        DB::table('gov_office_memberships')->insert(['user_id' => 2, 'location_id' => 10, 'status' => 'active']);
        $id = DB::table('gov_access_requests')->insertGetId(['user_id' => 2, 'location_id' => 10, 'ability' => 'catalog.office.adopt', 'reason' => 'Catalog duty', 'status' => 'pending']);
        $request = Request::create('/gov-store/access/requests/'.$id, 'POST', ['decision' => 'approved']);
        $request->setUserResolver(fn () => auth()->user());
        $request->setLaravelSession(app('session')->driver('array'));
        app()->instance('request', $request);
        app(AccessController::class)->review($request, $id, app(TenantContext::class), app(RoleAssignmentService::class));
        $grant = DB::table('gov_access_grants')->where('user_id', 2)->first();
        $this->assertSame('catalog_maintainer', $grant->role_slug);
        $this->assertEqualsWithDelta(now()->addDays(30)->timestamp, Carbon::parse($grant->expires_at)->timestamp, 2);
        $this->assertSame(0, DB::table('gov_office_responsibilities')->where('user_id', 2)->count());
        $other = DB::table('gov_access_requests')->insertGetId(['user_id' => 3, 'location_id' => 10, 'ability' => 'storeops.documents.view', 'reason' => 'Read documents', 'status' => 'pending']);
        DB::table('users')->where('id', 3)->update(['location_id' => 10]);
        $this->denied(fn () => app(AccessController::class)->review($request, $other, app(TenantContext::class), app(RoleAssignmentService::class)), 422);
    }

    public function test_reviewed_metadata_job_has_provenance_and_cannot_replay(): void
    {
        DB::table('users')->where('id', 1)->update(['permissions' => '{"superuser":1}']);
        $actor = User::withoutGlobalScopes()->findOrFail(1);
        auth()->setUser($actor);
        Bus::fake([ConvergeMetadataJob::class]);
        $request = Request::create('/gov-store/admin/metadata/converge', 'POST');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $actor);
        $request->setLaravelSession(app('session')->driver('array'));
        $route = app('router')->getRoutes()->getByName('gov.meta.converge.trigger');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
        $middleware = app(RequireGovAbility::class);
        $callback = fn ($r) => app(MetadataHealthController::class)->trigger($r);
        $response = $middleware->handle($request, $callback, 'metadata.national.manage');
        $this->assertSame(409, $response->getStatusCode());
        $this->assertSame(0, DB::table('gov_reviewed_operations')->count());
        $token = basename($response->getData(true)['review_url']);
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Reviewed metadata reconciliation']);
        $this->assertSame(302, $middleware->handle($request, $callback, 'metadata.national.manage')->getStatusCode());
        $operation = DB::table('gov_reviewed_operations')->first();
        $this->assertSame('queued', $operation->status);
        $this->assertSame(hash('sha256', $token), $operation->review_hash);
        $engine = Mockery::mock(ConvergenceEngine::class);
        $engine->shouldNotReceive('converge');
        $job = new ConvergeMetadataJob($operation->id);
        $job->handle($engine);
        $this->assertSame('completed', DB::table('gov_reviewed_operations')->value('status'));
        $this->denied(fn () => $job->handle($engine));
    }

    public function test_native_admin_cannot_trigger_national_metadata_and_office_hub_checks_remain_scoped(): void
    {
        DB::table('users')->where('id', 1)->update(['permissions' => '{"admin":1}']);
        auth()->setUser(User::withoutGlobalScopes()->findOrFail(1));
        $this->assertTrue(auth()->user()->hasAccess('admin'));
        $request = Request::create('/gov-store/admin/metadata/converge', 'POST');
        $request->setUserResolver(fn () => auth()->user());
        $this->denied(fn () => app(MetadataHealthController::class)->trigger($request));
        DB::table('gov_location_profiles')->insert(['location_id' => 10, 'office_admin_id' => 1]);
        $location = Location::withoutGlobalScopes()->findOrFail(10);
        $location->name = 'Authorized rename';
        app(TenantBoundaryService::class)->verify($location, 'update');
        $this->assertSame('Authorized rename', $location->name);
        $location->company_id = 40;
        $this->expectException(TenantBoundaryException::class);
        app(TenantBoundaryService::class)->verify($location, 'update');
    }

    public function test_native_reference_routes_keep_national_review_and_restore_the_reviewed_payload(): void
    {
        foreach (['categories', 'models', 'suppliers', 'manufacturers'] as $resource) {
            foreach (['update', 'destroy'] as $method) {
                $route = app('router')->getRoutes()->getByName($resource.'.'.$method);
                $this->assertContains('gov.can:catalog.reference.manage', $route->gatherMiddleware());
            }
        }
        $category = $this->category();
        $request = Request::create('/categories/'.$category->id, 'PUT', ['name' => 'Reviewed definition']);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => auth()->user());
        $request->setLaravelSession(app('session')->driver('array'));
        $route = app('router')->getRoutes()->getByName('categories.update');
        $route->bind($request);
        $request->setRouteResolver(fn () => $route);
        app()->instance('request', $request);
        $middleware = app(RequireGovAbility::class);
        $callback = function ($r) use ($category) {
            $category->name = $r->input('name');
            app(TenantBoundaryService::class)->verify($category, 'update');
            DB::table('categories')->where('id', $category->id)->update(['name' => $category->name]);

            return response()->json(['ok' => true]);
        };
        $this->assertSame(403, $middleware->handle($request, $callback, 'catalog.reference.manage')->getStatusCode());
        DB::table('users')->where('id', 1)->update(['permissions' => '{"superuser":1}']);
        auth()->setUser(User::withoutGlobalScopes()->findOrFail(1));
        $review = $middleware->handle($request, $callback, 'catalog.reference.manage');
        $this->assertSame(409, $review->getStatusCode());
        $token = basename($review->getData(true)['review_url']);
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Reviewed national definition', 'name' => 'Unreviewed replacement']);
        $this->assertSame(200, $middleware->handle($request, $callback, 'catalog.reference.manage')->getStatusCode());
        $this->assertSame('Reviewed definition', DB::table('categories')->where('id', $category->id)->value('name'));
        $request->attributes->remove('gov_national_reviewed');
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Replayed national definition']);
        $this->denied(fn () => $middleware->handle($request, $callback, 'catalog.reference.manage'), 422);
    }

    public function test_legacy_governance_migration_never_recovers_office_ownership_from_adoption(): void
    {
        Schema::drop('gov_reference_owners');
        foreach ([[1, 'global', null], [2, 'local', 20], [3, 'location', null]] as [$id,$type,$company]) {
            DB::table('categories')->insert(['id' => $id, 'name' => 'Legacy '.$id]);
            DB::table('gov_category_governance')->insert(['category_id' => $id, 'governance_type' => $type, 'created_by_company_id' => $company, 'created_by_user_id' => 2]);
        }
        DB::table('gov_tenant_scope_mappings')->insert(['reference_type' => 'category', 'reference_id' => 3, 'scope_type' => 'location', 'scope_id' => 10, 'is_active' => true]);
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_26_000001_create_gov_reference_owners.php'))->up();
        $this->assertSame(['national', 'company', 'unresolved'], DB::table('gov_reference_owners')->orderBy('reference_id')->pluck('owner_type')->all());
        $category = Category::withoutGlobalScopes()->findOrFail(3);
        app(ReferenceOwnershipService::class)->assign($category, 'office', 10, 1, 'Evidence reviewed for the office');
        $this->assertSame(2, (int) DB::table('gov_category_governance')->where('category_id', 3)->value('created_by_user_id'));
        DB::table('gov_location_profiles')->insert(['location_id' => 10, 'office_admin_id' => 1]);
        $defaults = app(ProductTypeFinanceDefault::class);
        $this->assertSame(['office', 10], $defaults->scope('category', 3));
        $this->assertTrue($defaults->canEdit(auth()->user(), 3));
        $this->assertFalse($defaults->canEdit(auth()->user(), 1), 'An office catalog manager cannot edit national Finance defaults.');
        $this->assertFalse($defaults->canEdit(auth()->user(), 2), 'An office administrator cannot edit company Finance defaults.');
        app(TenantContext::class)->locationId = 11;
        $this->assertFalse($defaults->canEdit(auth()->user(), 3));
    }

    public function test_scoped_reference_creation_is_private_and_can_be_used_by_the_creating_office(): void
    {
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'catalog_maintainer']);
        $request = Request::create('/gov-store/operations/catalog/references', 'POST', ['reference_type' => 'supplier', 'name' => 'Scoped supplier']);
        $request->setUserResolver(fn () => auth()->user());
        $result = app(ReferenceProvisioningController::class)->store($request, app(TenantContext::class));
        $this->assertSame(302, $result->getStatusCode());
        $supplier = Supplier::firstOrFail();
        $this->assertSame(['office', 10], app(ReferenceOwnershipService::class)->owner($supplier));
        $this->assertTrue(app(CategoryBoundaryPolicy::class)->canMutate($supplier, app(TenantContext::class)));
        app(TenantContext::class)->locationId = 30;
        app(TenantContext::class)->companyId = 40;
        $this->assertNull(Supplier::find($supplier->id));

        $context = app(TenantContext::class);
        $context->isGlobal = true;
        DB::table('users')->where('id', 1)->update(['permissions' => json_encode(['superuser' => '1'])]);
        auth()->setUser(User::withoutGlobalScopes()->findOrFail(1));
        $request->replace(['reference_type' => 'supplier', 'name' => 'National supplier']);
        $request->attributes->set('gov_national_reviewed', 'tenant.scope.manage');
        $this->denied(fn () => app(ReferenceProvisioningController::class)->store($request, $context));
        $this->assertSame(1, DB::table('suppliers')->count());
        $request->attributes->set('gov_national_reviewed', 'catalog.master.manage');
        $this->assertSame(302, app(ReferenceProvisioningController::class)->store($request, $context)->getStatusCode());
        $national = Supplier::withoutGlobalScopes()->where('name', 'National supplier')->firstOrFail();
        $this->assertSame(['national', null], app(ReferenceOwnershipService::class)->owner($national));
    }

    public function test_scoped_model_and_manufacturer_creation_keep_ownership_and_validate_related_ids(): void
    {
        $engine = Mockery::mock(ConvergenceEngine::class);
        $engine->shouldReceive('converge')->once()->andReturn(false);
        app()->instance(ConvergenceEngine::class, $engine);
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $category = $this->category();
        $controller = app(ReferenceProvisioningController::class);
        $request = Request::create('/gov-store/operations/catalog/references', 'POST', ['reference_type' => 'manufacturer', 'name' => 'Local manufacturer']);
        $request->setUserResolver(fn () => auth()->user());
        $this->assertSame(302, $controller->store($request, app(TenantContext::class))->getStatusCode());
        $manufacturer = Manufacturer::firstOrFail();
        $this->assertSame(['office', 10], app(ReferenceOwnershipService::class)->owner($manufacturer));
        $request->replace(['reference_type' => 'model', 'name' => 'Local model', 'category_id' => $category->id, 'manufacturer_id' => $manufacturer->id]);
        $this->assertSame(302, $controller->store($request, app(TenantContext::class))->getStatusCode());
        $model = AssetModel::firstOrFail();
        $this->assertSame(['office', 10], app(ReferenceOwnershipService::class)->owner($model));
        $foreign = $this->category('office', 30);
        $request->replace(['reference_type' => 'model', 'name' => 'Forbidden model', 'category_id' => $foreign->id]);
        try {
            $controller->store($request, app(TenantContext::class));
            $this->fail('Foreign category accepted.');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(1, DB::table('models')->count());
        }
    }

    public function test_native_office_without_reviewed_membership_never_becomes_a_working_office(): void
    {
        DB::table('gov_office_memberships')->delete();
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        app(InitializeTenantContext::class)->handle(Request::create('/'), fn () => response('ok'));
        $context = app(TenantContext::class);
        $this->assertNull($context->locationId);
        $this->assertNull($context->membershipId);
        $this->assertSame([], $context->allowedLocationIds);
        $this->assertFalse(auth()->user()->hasAccess('assets.checkout'));
        $this->assertFalse(app(GovAccess::class)->decide(auth()->user(), 'catalog.office.adopt')->allowed);
    }
}
