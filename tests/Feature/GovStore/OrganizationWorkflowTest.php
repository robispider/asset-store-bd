<?php

namespace Tests\Feature\GovStore;

use App\Models\Location;
use App\Models\User;
use GovStore\Classification\Jobs\ExecuteStarterTemplateJob;
use GovStore\Classification\Services\BulkAdoptionService;
use GovStore\Classification\Services\OfficeStarterCatalog;
use GovStore\Organization\Events\OfficeProvisioned;
use GovStore\Organization\Services\OfficeConfigurationService;
use GovStore\Organization\Services\OfficeLifecycleService;
use GovStore\Organization\Services\OfficeProvisioningService;
use GovStore\Organization\Services\OfficeReadinessService;
use GovStore\Organization\Services\OfficeRequestIntake;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use GovStore\TenantScope\Services\NationalChangeReview;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Every schema and write in this class is isolated in SQLite memory. */
class OrganizationWorkflowTest extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'govstore-access.mode' => 'enforce', 'queue.default' => 'sync',
            'logging.default' => 'testing', 'logging.channels.testing' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class],
            'starter_templates.office_types.default' => ['Office Furniture', 'Basic Stationery']]);
        DB::purge('sqlite');
        app(\GovStore\TenantScope\Services\SchemaKnowledge::class)->clear();
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('first_name')->default('Test'); $t->string('last_name')->nullable();
            $t->string('username')->nullable(); $t->string('email')->nullable(); $t->string('jobtitle')->nullable();
            $t->integer('company_id')->nullable(); $t->integer('location_id')->nullable();
            $t->boolean('activated')->default(true); $t->text('permissions')->nullable();
            $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('permission_groups', function (Blueprint $t) { $t->increments('id'); $t->text('permissions')->nullable(); });
        Schema::create('users_groups', function (Blueprint $t) { $t->integer('user_id'); $t->integer('group_id'); });
        Schema::create('companies', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('locations', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->integer('company_id')->nullable(); $t->integer('parent_id')->nullable();
            $t->integer('manager_id')->nullable(); $t->string('city')->nullable(); $t->string('state')->nullable();
            $t->string('country')->nullable(); $t->string('currency')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('action_logs', function (Blueprint $t) {
            $t->increments('id'); $t->string('item_type'); $t->integer('item_id'); $t->integer('created_by')->nullable();
            $t->string('action_type'); $t->string('remote_ip')->nullable(); $t->string('user_agent')->nullable();
            $t->string('action_source')->nullable(); $t->integer('company_id')->nullable();
            $t->text('log_meta')->nullable(); $t->timestamp('action_date')->nullable(); $t->timestamps();
        });
        Schema::create('gov_geo_areas', function (Blueprint $t) {
            $t->integer('GeoAreaId')->primary(); $t->string('hid'); $t->integer('geo_code'); $t->string('geo_type');
            $t->string('en_name'); $t->string('bn_name'); $t->integer('GeoLevel')->default(1);
        });
        require_once base_path('packages/gov-store/organization/src/database/migrations/2024_01_04_000000_create_gov_organization_tables.php');
        (new \CreateGovOrganizationTables)->up();
        Schema::table('gov_location_profiles', fn (Blueprint $t) => $t->string('office_type')->default('default'));
        Schema::create('gov_office_memberships', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('location_id'); $t->string('status');
            $t->boolean('is_home_office')->default(true); $t->date('valid_until')->nullable(); $t->timestamps();
        });
        Schema::create('gov_office_responsibilities', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('location_id'); $t->string('role_slug'); $t->timestamps();
        });
        Schema::create('gov_company_admins', function (Blueprint $t) { $t->increments('id'); $t->integer('user_id'); $t->integer('company_id'); });
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        Schema::create('categories', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('category_type');
            $t->boolean('checkin_email')->default(false); $t->boolean('require_acceptance')->default(false);
            $t->boolean('use_default_eula')->default(false); $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('models', function (Blueprint $t) { $t->increments('id'); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('gov_tenant_scope_mappings', function (Blueprint $t) {
            $t->increments('id'); $t->string('reference_type'); $t->integer('reference_id'); $t->string('scope_type');
            $t->integer('scope_id'); $t->boolean('is_active')->default(true); $t->timestamps();
            $t->unique(['reference_type', 'reference_id', 'scope_type', 'scope_id']);
        });
        (require base_path('packages/gov-store/classification/src/database/migrations/2024_04_01_000000_create_gov_catalog_tables.php'))->up();
        (require base_path('packages/gov-store/classification/src/database/migrations/2026__08_04_create_gov_catalog_collections_table.php'))->up();
        (require base_path('packages/gov-store/classification/src/database/migrations/2024_01_06_000000_create_gov_category_governance_table.php'))->up();
        (require base_path('packages/gov-store/classification/src/database/migrations/2026_10_08_000001_create_office_starter_runs.php'))->up();
        DB::table('companies')->insert([['id' => 20, 'name' => 'Ministry A'], ['id' => 40, 'name' => 'Ministry B']]);
        DB::table('users')->insert([
            ['id' => 1, 'company_id' => 20, 'location_id' => 10, 'permissions' => '{"superuser":1}'],
            ['id' => 2, 'company_id' => 20, 'location_id' => 10, 'permissions' => '{}'],
            ['id' => 3, 'company_id' => 20, 'location_id' => 10, 'permissions' => '{}'],
            ['id' => 4, 'company_id' => 40, 'location_id' => 30, 'permissions' => '{"admin":1}'],
        ]);
        DB::table('gov_geo_areas')->insert([
            ['GeoAreaId' => 1, 'hid' => '/10/', 'geo_code' => 10, 'geo_type' => 'divison', 'en_name' => 'Division A', 'bn_name' => 'ক'],
            ['GeoAreaId' => 2, 'hid' => '/10/11/', 'geo_code' => 11, 'geo_type' => 'district', 'en_name' => 'District A', 'bn_name' => 'খ'],
            ['GeoAreaId' => 3, 'hid' => '/10/12/', 'geo_code' => 12, 'geo_type' => 'district', 'en_name' => 'District B', 'bn_name' => 'গ'],
            ['GeoAreaId' => 4, 'hid' => '/20/', 'geo_code' => 20, 'geo_type' => 'district', 'en_name' => 'Outside', 'bn_name' => 'ঘ'],
        ]);
        DB::table('locations')->insert([['id' => 10, 'name' => 'Office A', 'company_id' => 20], ['id' => 30, 'name' => 'Office B', 'company_id' => 40]]);
        DB::table('gov_location_profiles')->insert([
            ['location_id' => 10, 'geo_area_id' => 2, 'office_admin_id' => 2, 'lifecycle_status' => 'configured'],
            ['location_id' => 30, 'geo_area_id' => 4, 'office_admin_id' => 4, 'lifecycle_status' => 'configured'],
        ]);
        DB::table('gov_office_memberships')->insert([
            ['user_id' => 2, 'location_id' => 10, 'status' => 'active'], ['user_id' => 3, 'location_id' => 10, 'status' => 'active'],
            ['user_id' => 4, 'location_id' => 30, 'status' => 'active'],
        ]);
        $this->actor(1);
        $this->catalog();
    }

    private function actor(int $id): User
    {
        app(TenantContext::class)->reset();
        $user = User::withoutGlobalScopes()->findOrFail($id);
        auth()->setUser($user);
        $context = app(TenantContext::class);
        $context->locationId = (int) $user->location_id;
        $context->companyId = (int) $user->company_id;

        return $user;
    }

    private function catalog(): void
    {
        DB::table('gov_catalog_nodes')->insert([
            ['code' => '10000000', 'scheme' => 'UNSPSC', 'version' => 'test', 'level' => 1, 'title_en' => 'Furniture', 'hid' => '/10000000/'],
            ['code' => '10000001', 'scheme' => 'UNSPSC', 'version' => 'test', 'level' => 4, 'title_en' => 'Test desk', 'hid' => '/10000000/10000001/'],
            ['code' => '20000001', 'scheme' => 'UNSPSC', 'version' => 'test', 'level' => 4, 'title_en' => 'Test pen', 'hid' => '/20000001/'],
        ]);
        DB::table('gov_catalog_collections')->insert([['id' => 1, 'name' => 'Office Furniture'], ['id' => 2, 'name' => 'Basic Stationery']]);
        DB::table('gov_catalog_collection_nodes')->insert([['collection_id' => 1, 'code' => '10000000'], ['collection_id' => 2, 'code' => '20000001']]);
    }

    private function event(int $office = 10, int $admin = 2): OfficeProvisioned
    {
        return new OfficeProvisioned(Location::withoutGlobalScopes()->findOrFail($office), 1, 'default', $admin);
    }

    private function change(string $action, string $status = 'configured', array $extra = []): array
    {
        return $extra + ['action' => $action, 'expected_status' => $status, 'expected_geo_area_id' => 2, 'reason' => 'Isolated test transition', 'confirmation' => 'CHANGE'];
    }

    private function denied(callable $action, int $status): void
    {
        try {
            $action();
            $this->fail('The forbidden action succeeded');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }

    public function test_actual_provisioning_then_admin_assignment_adopts_the_starter_after_commit(): void
    {
        $levels = [];
        app('events')->listen(OfficeProvisioned::class, function () use (&$levels) { $levels[] = DB::transactionLevel(); });
        $office = app(OfficeProvisioningService::class)->provisionOffice([
            'name' => 'Fresh office', 'geo_area_id' => 2, 'company_id' => 20, 'office_type' => 'default',
        ], 1);
        $this->assertSame(0, DB::table('gov_office_starter_runs')->count());
        DB::table('gov_office_memberships')->insert(['user_id' => 2, 'location_id' => $office->id, 'status' => 'active']);
        app(OfficeProvisioningService::class)->assignOfficeAdmin($office->id, 2, 1);

        $this->assertSame([0, 0], $levels);
        $this->assertSame('completed', DB::table('gov_office_starter_runs')->value('status'));
        $this->assertSame(2, DB::table('categories')->count());
        $this->assertSame(2, DB::table('gov_tenant_scope_mappings')->where('scope_id', $office->id)->count());
        $this->assertSame([2], DB::table('gov_category_governance')->distinct()->pluck('created_by_user_id')->all());
        $this->assertSame(1, (int) auth()->id());
    }

    public function test_duplicate_delivery_and_collection_changes_do_not_replace_or_duplicate_the_bundle(): void
    {
        Bus::fake();
        app(OfficeStarterCatalog::class)->schedule($this->event());
        $first = DB::table('gov_office_starter_runs')->value('snapshot');
        DB::table('gov_catalog_collection_nodes')->where('collection_id', 2)->delete();
        app(OfficeStarterCatalog::class)->schedule($this->event());
        $this->assertSame($first, DB::table('gov_office_starter_runs')->value('snapshot'));
        $jobs = Bus::dispatched(ExecuteStarterTemplateJob::class);
        $this->assertCount(2, $jobs);
        foreach ($jobs as $job) {
            $job->handle(app(BulkAdoptionService::class));
        }
        $this->assertSame(2, DB::table('categories')->count());
        $this->assertSame(2, DB::table('gov_tenant_scope_mappings')->count());
        $this->assertSame(1, DB::table('gov_office_starter_runs')->count());
        $this->assertSame('completed', DB::table('gov_office_starter_runs')->value('status'));
    }

    public function test_missing_collection_has_visible_failure_and_can_be_repaired_and_retried(): void
    {
        DB::table('gov_catalog_collections')->where('id', 2)->update(['is_active' => false]);
        app(OfficeStarterCatalog::class)->schedule($this->event());
        $run = DB::table('gov_office_starter_runs')->first();
        $this->assertSame('failed', $run->status);
        $this->assertNotNull($run->failure_reference);
        $this->assertNull($run->snapshot);
        $this->assertSame(0, DB::table('categories')->count());
        DB::table('gov_catalog_collections')->where('id', 2)->update(['is_active' => true]);
        app(OfficeStarterCatalog::class)->schedule($this->event());
        $this->assertSame('completed', DB::table('gov_office_starter_runs')->value('status'));
        $this->assertSame(2, DB::table('categories')->count());
    }

    public function test_failed_adoption_rolls_back_and_retries_exact_saved_payload(): void
    {
        Bus::fake();
        app(OfficeStarterCatalog::class)->schedule($this->event());
        $job = Bus::dispatched(ExecuteStarterTemplateJob::class)->first();
        $real = app(BulkAdoptionService::class);
        $failure = Mockery::mock(BulkAdoptionService::class);
        $failure->shouldReceive('execute')->once()->andReturnUsing(function (...$arguments) use ($real) {
            $real->execute(...$arguments);
            throw new \RuntimeException('Simulated failure after adoption');
        });
        try {
            $job->handle($failure);
            $this->fail('Expected failure');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated failure after adoption', $e->getMessage());
        }
        $this->assertSame(0, DB::table('categories')->count());
        $this->assertSame(0, DB::table('gov_tenant_scope_mappings')->count());
        $this->assertSame('failed', DB::table('gov_office_starter_runs')->value('status'));
        $job->handle($real);
        $this->assertSame(2, DB::table('categories')->count());
        $this->assertSame('completed', DB::table('gov_office_starter_runs')->value('status'));
    }

    public function test_revoked_actor_changed_reference_and_forged_bundle_cannot_adopt(): void
    {
        Bus::fake();
        app(OfficeStarterCatalog::class)->schedule($this->event());
        $job = Bus::dispatched(ExecuteStarterTemplateJob::class)->first();
        DB::table('users')->where('id', 2)->update(['activated' => false]);
        try {
            $job->handle(app(BulkAdoptionService::class));
            $this->fail('Revoked actor adopted');
        } catch (AuthorizationException $e) {
            $this->assertSame(0, DB::table('categories')->count());
        }
        $this->assertSame(1, (int) auth()->id());
        DB::table('users')->where('id', 2)->update(['activated' => true]);
        $run = DB::table('gov_office_starter_runs')->first();
        $snapshot = json_decode($run->snapshot, true);
        $snapshot[0]['category_type'] = 'asset';
        $this->denied(fn () => (new ExecuteStarterTemplateJob($snapshot, 'location', 10, 2, $run->id))->handle(app(BulkAdoptionService::class)), 409);
        DB::table('gov_catalog_nodes')->where('code', '10000001')->update(['version' => 'changed']);
        try {
            $job->handle(app(BulkAdoptionService::class));
            $this->fail('Changed reference adopted');
        } catch (\RuntimeException $e) {
            $this->assertSame(0, DB::table('gov_tenant_scope_mappings')->count());
        }
        $this->assertSame(0, DB::table('categories')->count());
    }

    public function test_suspension_is_audited_and_readiness_configuration_and_new_intake_cannot_undo_it(): void
    {
        $this->actor(2);
        app(OfficeLifecycleService::class)->transition(10, 2, $this->change('suspend'));
        $this->assertSame('suspended', DB::table('gov_location_profiles')->where('location_id', 10)->value('lifecycle_status'));
        $this->assertSame(1, DB::table('gov_organization_activity_logs')->where('event_type', 'office_suspend')->count());
        $this->denied(fn () => app(OfficeLifecycleService::class)->transition(10, 2, $this->change('suspend')), 409);
        $this->denied(fn () => app(OfficeConfigurationService::class)->saveRoles(10, ['storekeeper_id' => 3], 2), 409);
        $this->denied(fn () => app(OfficeRequestIntake::class)->assertOpen([10], true), 409);
        $this->assertFalse(app(OfficeReadinessService::class)->evaluateAndTransition(10)['is_operational']);
        $this->assertSame('suspended', DB::table('gov_location_profiles')->where('location_id', 10)->value('lifecycle_status'));
        app(OfficeLifecycleService::class)->transition(10, 2, $this->change('resume', 'suspended'));
        $this->assertSame('configured', DB::table('gov_location_profiles')->where('location_id', 10)->value('lifecycle_status'));
    }

    public function test_foreign_office_native_admin_and_incomplete_terminal_clearance_fail_without_audit_success(): void
    {
        $this->actor(4);
        $this->denied(fn () => app(OfficeLifecycleService::class)->transition(10, 4, $this->change('suspend')), 404);
        foreach (['close', 'merge'] as $action) {
            $this->denied(fn () => app(OfficeLifecycleService::class)->transition(10, 1, $this->change($action)), 409);
        }
        $this->assertSame(0, DB::table('gov_organization_activity_logs')->count());
        $this->assertSame('configured', DB::table('gov_location_profiles')->where('location_id', 10)->value('lifecycle_status'));
    }

    public function test_relocation_checks_both_territories_and_clears_geographic_verification(): void
    {
        DB::table('gov_ict_jurisdictions')->insert(['user_id' => 3, 'geo_area_id' => 1]);
        DB::table('gov_location_profiles')->where('location_id', 10)->update(['geo_area_verified_at' => now(), 'geo_area_verified_by' => 1]);
        $this->denied(fn () => app(OfficeLifecycleService::class)->transition(10, 3, $this->change('relocate', 'configured', ['geo_area_id' => 4])), 404);
        $this->denied(fn () => app(OfficeLifecycleService::class)->transition(30, 3, $this->change('relocate', 'configured', ['geo_area_id' => 3])), 404);
        app(OfficeLifecycleService::class)->transition(10, 3, $this->change('relocate', 'configured', ['geo_area_id' => 3]));
        $profile = DB::table('gov_location_profiles')->where('location_id', 10)->first();
        $this->assertSame(3, (int) $profile->geo_area_id);
        $this->assertNull($profile->geo_area_verified_at);
        $this->assertNull($profile->geo_area_verified_by);
        $this->assertSame('District B', DB::table('locations')->where('id', 10)->value('state'));
        $this->assertSame(1, DB::table('gov_organization_activity_logs')->where('event_type', 'office_relocate')->count());
    }

    public function test_role_assignment_requires_active_local_membership_and_preserves_other_responsibilities(): void
    {
        $this->actor(2);
        DB::table('gov_office_responsibilities')->insert(['user_id' => 3, 'location_id' => 10, 'role_slug' => 'committee_registrar']);
        $this->denied(fn () => app(OfficeConfigurationService::class)->saveRoles(10, ['storekeeper_id' => 4], 2), 422);
        app(OfficeConfigurationService::class)->saveRoles(10, ['primary_approver_id' => 2, 'storekeeper_id' => 3], 2);
        $this->assertSame('operational', DB::table('gov_location_profiles')->where('location_id', 10)->value('lifecycle_status'));
        $this->assertDatabaseHas('gov_office_responsibilities', ['role_slug' => 'committee_registrar', 'user_id' => 3]);
        DB::table('gov_office_memberships')->where('user_id', 3)->update(['valid_until' => now()->subDay()->toDateString()]);
        $this->assertFalse(app(OfficeReadinessService::class)->evaluateAndTransition(10)['is_operational']);
        $this->denied(fn () => app(OfficeConfigurationService::class)->saveRoles(10, ['storekeeper_id' => 3], 2), 422);
        $this->assertDatabaseHas('gov_office_responsibilities', ['role_slug' => 'storekeeper', 'user_id' => 3]);
    }

    public function test_organization_national_review_binds_exact_assignment_and_rejects_changes_expiry_and_replay(): void
    {
        $service = app(NationalChangeReview::class);
        foreach ([['gov.org.jurisdictions.store', '/gov-store/admin/organization/jurisdictions/store', 'gov_ict_jurisdictions', 'geo_area_id', 1, 4],
            ['gov.org.company_admins.store', '/gov-store/office/company-admins/store', 'gov_company_admins', 'company_id', 20, 40]] as [$route, $path, $table, $column, $old, $new]) {
            DB::table($table)->insert(['user_id' => 3, $column => $old]);
            $request = Request::create($path, 'POST', ['user_id' => 3, $column => $new]);
            $request->headers->set('Accept', 'application/json');
            $request->setUserResolver(fn () => User::withoutGlobalScopes()->findOrFail(1));
            $request->setRouteResolver(fn () => Route::getRoutes()->getByName($route));
            $session = app('session')->driver('array');
            $request->setLaravelSession($session);
            $review = $service->handle($request, 'organization.national.manage');
            $this->assertSame(409, $review->getStatusCode());
            $token = basename($review->getData(true)['review_url']);
            DB::table($table)->where('user_id', 3)->update([$column => $new]);
            $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Review isolated organization assignment']);
            $this->denied(fn () => $service->handle($request, 'organization.national.manage'), 409);
            DB::table($table)->where('user_id', 3)->update([$column => $old]);
            $entry = $session->get('gov_national_reviews.'.$token);
            $session->put('gov_national_reviews.'.$token, ['expires' => time() - 1] + $entry);
            $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Review isolated organization assignment']);
            $this->denied(fn () => $service->handle($request, 'organization.national.manage'), 422);
            $session->put('gov_national_reviews.'.$token, $entry);
            $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Review isolated organization assignment', 'user_id' => 4, $column => 999]);
            $this->assertNull($service->handle($request, 'organization.national.manage'));
            $this->assertSame(3, $request->input('user_id'));
            $this->assertSame($new, $request->input($column));
            $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Review isolated organization assignment']);
            $this->denied(fn () => $service->handle($request, 'organization.national.manage'), 422);
        }
        $this->assertSame(2, DB::table('gov_access_review_uses')->count());
        foreach ([3, 4] as $actorId) {
            $this->assertFalse(app(\GovStore\TenantScope\Services\GovAccess::class)->decide($this->actor($actorId), 'organization.national.manage')->allowed);
        }
    }

    public function test_paused_delivery_office_cannot_bypass_intake_and_existing_requests_remain_available(): void
    {
        DB::table('gov_location_profiles')->where('location_id', 30)->update(['lifecycle_status' => 'suspended']);
        $this->denied(fn () => app(OfficeRequestIntake::class)->assertOpen([10, 30], true), 409);
        $middleware = app(\GovStore\Organization\Http\Middleware\EnsureOfficeRequestIntake::class);
        $this->actor(4);
        foreach (['/gov-requests/catalog', '/gov-requests/basket/submit'] as $path) {
            $this->denied(fn () => $middleware->handle(Request::create($path), fn () => response('wrong')), 409);
        }
        foreach (['/gov-requests/admin/7/process', '/gov-requests/my-requests/7/return'] as $path) {
            $this->assertSame('ok', $middleware->handle(Request::create($path), fn () => response('ok'))->getContent());
        }
    }

    public function test_lifecycle_confirmation_is_validated_and_bilingual_controls_render(): void
    {
        try {
            app(OfficeLifecycleService::class)->transition(10, 1, $this->change('suspend', 'configured', ['confirmation' => 'wrong']));
            $this->fail('Invalid confirmation accepted');
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->assertArrayHasKey('confirmation', $e->errors());
        }
        $this->assertSame(0, DB::table('gov_organization_activity_logs')->count());
        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale);
            $html = view('govorg::provisioning.lifecycle', [
                'location' => Location::withoutGlobalScopes()->findOrFail(10),
                'profile' => \GovStore\Organization\Models\LocationProfile::where('location_id', 10)->firstOrFail(), 'starter' => null,
            ])->render();
            $this->assertStringContainsString(__('organization_labels::orglabel.lifecycle_title'), $html);
            $this->assertStringContainsString('name="expected_geo_area_id"', $html);
            $this->assertStringContainsString(__('organization_labels::orglabel.lifecycle_clearance_pending'), $html);
        }
    }
}
