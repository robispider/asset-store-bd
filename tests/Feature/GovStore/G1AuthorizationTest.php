<?php

namespace Tests\Feature\GovStore;

use App\Models\Consumable;
use App\Models\User;
use GovStore\Classification\Services\CatalogReview;
use GovStore\CustomRequests\Http\Controllers\GovApprovalController;
use GovStore\CustomRequests\Http\Controllers\GovFulfillmentController;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\OfficeMembership\Services\RoleAssignmentService;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\DocumentItem;
use GovStore\StoreOperations\Models\DocumentItemMeta;
use GovStore\StoreOperations\Policies\DocumentPolicy;
use GovStore\StoreOperations\Services\DocumentValidationService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Controllers\AccessController;
use GovStore\TenantScope\Http\Middleware\InitializeTenantContext;
use GovStore\TenantScope\Http\Middleware\RequireGovAbility;
use GovStore\TenantScope\Navigation\MenuRegistry;
use GovStore\TenantScope\Services\AssignmentResolver;
use GovStore\TenantScope\Services\CapabilityProfileResolver;
use GovStore\TenantScope\Services\EffectivePermissionSet;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\TenantScope\Services\NationalChangeReview;
use GovStore\TenantScope\Services\SnipePermissionAdapter;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Deliberately uses only an in-memory SQLite database, never the application's database. */
class G1AuthorizationTest extends TestCase
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'govstore-access.mode' => 'enforce', 'cache.default' => 'array']);
        DB::purge('sqlite');
        foreach (['gov_company_admins' => ['user_id', 'company_id'], 'gov_ict_jurisdictions' => ['user_id'],
            'gov_location_profiles' => ['location_id', 'office_admin_id'], 'gov_office_responsibilities' => ['user_id', 'location_id', 'role_slug']] as $table => $columns) {
            Schema::create($table, function (Blueprint $table) use ($columns) {
                $table->increments('id');
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                } $table->timestamps();
            });
        }
        Schema::create('users', function (Blueprint $table) {
            $table->increments('id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->integer('location_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
        });
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        $context = new TenantContext;
        $context->isActive = true;
        $context->locationId = 10;
        $context->companyId = 20;
        app()->instance(TenantContext::class, $context);
    }

    private function actor(string $role = 'employee', int $id = 1): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->shouldReceive('isSuperUser')->andReturn($role === 'superuser');
        $user->shouldReceive('hasAccess')->andReturn(false);
        auth()->setUser($user);
        if (in_array($role, ['storekeeper', 'primary_approver', 'final_approver'])) {
            DB::table('gov_office_responsibilities')->insert(['user_id' => $id, 'location_id' => 10, 'role_slug' => $role]);
        }
        if ($role === 'office_admin') {
            DB::table('gov_location_profiles')->insert(['location_id' => 10, 'office_admin_id' => $id]);
        }
        if ($role === 'company_admin') {
            DB::table('gov_company_admins')->insert(['company_id' => 20, 'user_id' => $id]);
        }
        if ($role === 'ict_officer') {
            DB::table('gov_ict_jurisdictions')->insert(['user_id' => $id]);
        }

        return $user;
    }

    public function test_every_g1_route_declares_a_registered_ability(): void
    {
        $count = 0;
        foreach (Route::getRoutes() as $route) {
            if (! preg_match('#^(gov-store/operations(?:/|$)|gov-store/access(?:/|$)|gov-requests(?:/|$)|admin/catalog(?:/|$))#', $route->uri())) {
                continue;
            }
            $abilities = array_values(array_filter($route->gatherMiddleware(), fn ($m) => str_starts_with($m, 'gov.can:')));
            $this->assertCount(1, $abilities, $route->uri());
            $this->assertArrayHasKey(substr($abilities[0], 8), config('govstore-abilities'), $route->uri());
            if ($controller = $route->getAction('controller')) {
                [$class, $method] = explode('@', $controller);
                $this->assertTrue(method_exists($class, $method), $route->uri().' has no controller method');
            }
            $count++;
        }
        $this->assertGreaterThanOrEqual(95, $count);
        $this->assertNotSame(Route::getRoutes()->getByName('storeops.admin.rules.assign')->uri(), Route::getRoutes()->getByName('storeops.admin.rules.assign_gpo')->uri());
    }

    public static function roleMatrix(): array
    {
        return [
            ['employee', 'storeops.documents.view', false], ['employee', 'storeops.documents.post', false],
            ['storekeeper', 'storeops.documents.post', true], ['storekeeper', 'storeops.rules.publish', false],
            ['primary_approver', 'storeops.documents.view', true], ['primary_approver', 'storeops.documents.post', false],
            ['office_admin', 'catalog.office.adopt', true], ['office_admin', 'storeops.documents.post', false],
            ['company_admin', 'catalog.office.adopt', true], ['company_admin', 'catalog.master.manage', false],
            ['superuser', 'catalog.master.manage', true], ['employee', 'made.up.ability', false],
            ['ict_officer', 'access.matrix', true], ['ict_officer', 'access.audit', false],
        ];
    }

    #[DataProvider('roleMatrix')]
    public function test_role_ability_matrix_and_gates(string $role, string $ability, bool $allowed): void
    {
        $user = $this->actor($role);
        $this->assertSame($allowed, app(GovAccess::class)->decide($user, $ability)->allowed);
        $this->assertSame($allowed, Gate::forUser($user)->allows($ability));
    }

    public function test_all_seeded_roles_and_abilities_have_identical_menu_and_gate_results(): void
    {
        foreach (['employee', 'storekeeper', 'primary_approver', 'final_approver', 'office_admin', 'company_admin', 'ict_officer', 'superuser'] as $index => $role) {
            $user = $this->actor($role, $index + 1);
            $registry = new MenuRegistry;
            foreach (config('govstore-abilities') as $ability => $definition) {
                $registry->register(['id' => $ability, 'title' => $ability, 'permission' => $ability]);
            }
            $visible = array_map(fn ($item) => $item->id, $registry->tree());
            foreach (array_keys(config('govstore-abilities')) as $ability) {
                $this->assertSame(Gate::forUser($user)->allows($ability), in_array($ability, $visible), $role.' '.$ability);
            }
            $this->assertCount(count($visible), $registry->tree());
        }
    }

    public function test_employee_gets_json_denials_before_every_g1_mutation(): void
    {
        $user = $this->actor();
        $checked = 0;
        foreach (Route::getRoutes() as $route) {
            if (! preg_match('#^(gov-store/operations(?:/|$)|admin/catalog(?:/|$))#', $route->uri()) || ! array_intersect($route->methods(), ['POST', 'DELETE', 'PUT', 'PATCH'])) {
                continue;
            }
            $middleware = collect($route->gatherMiddleware())->first(fn ($m) => str_starts_with($m, 'gov.can:'));
            $ability = substr($middleware, 8);
            $request = Request::create('/'.$route->uri(), $route->methods()[0]);
            $request->headers->set('Accept', 'application/json');
            $request->setUserResolver(fn () => $user);
            app()->instance('request', $request);
            $response = app(RequireGovAbility::class)->handle($request, fn () => throw new \RuntimeException('Mutation was reached'), $ability);
            $this->assertSame(403, $response->getStatusCode(), $route->uri());
            $this->assertArrayHasKey('helpers', $response->getData(true));
            $this->assertArrayHasKey('reference_id', $response->getData(true));
            $checked++;
        }
        $this->assertGreaterThanOrEqual(22, $checked);
    }

    public function test_shadow_never_blocks_office_abilities_but_always_enforces_national_and_unknown_ones(): void
    {
        config(['govstore-access.mode' => 'shadow']);
        $user = $this->actor();
        $request = Request::create('/gov-store/operations/hub');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);
        $middleware = app(RequireGovAbility::class);
        for ($i = 0; $i < 3; $i++) {
            $this->assertSame(200, $middleware->handle($request, fn () => response()->json(['ok' => true]), 'storeops.documents.view')->getStatusCode());
        }
        $this->assertSame(1, DB::table('gov_access_events')->where('outcome', 'shadow')->count());
        foreach (['storeops.rules.publish', 'catalog.master.manage', 'unknown.action'] as $ability) {
            $this->assertSame(403, $middleware->handle($request, fn () => response('bad'), $ability)->getStatusCode());
        }
        config(['govstore-access.mode' => 'typo']);
        $this->assertTrue(app(GovAccess::class)->enforces('storeops.documents.view'));
    }

    public function test_posted_cancelled_and_unknown_states_cannot_be_modified_and_type_office_checks_always_hold(): void
    {
        $this->actor('storekeeper');
        $policy = app(DocumentPolicy::class);
        $document = new Document(['type' => 'receipt', 'status' => 'DRAFT', 'location_id' => 10, 'company_id' => 20]);
        $policy->check($document, 'receipt', 'draft');
        $policy->check($document, 'receipt', 'post');
        foreach (['POSTED', 'CANCELLED', 'garbage'] as $state) {
            $document->status = $state;
            foreach (['draft', 'post'] as $action) {
                $this->assertHttpStatus(409, fn () => $policy->check($document, 'receipt', $action));
            }
        }
        $document->status = 'READY';
        $policy->check($document, 'receipt', 'post');
        $this->assertHttpStatus(409, fn () => $policy->check($document, 'receipt', 'draft'));
        $this->assertHttpStatus(404, fn () => $policy->check($document, 'issue'));
        $document->location_id = 11;
        config(['govstore-access.mode' => 'shadow']);
        $this->assertHttpStatus(404, fn () => $policy->check($document, 'receipt'));
    }

    private function assertHttpStatus(int $status, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected HTTP '.$status);
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_temporary_cover_expires_and_role_changes_apply_without_logging_out(): void
    {
        $user = $this->actor();
        $access = app(GovAccess::class);
        $this->assertFalse($access->decide($user, 'storeops.documents.post')->allowed);
        DB::table('gov_access_grants')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper', 'expires_at' => now()->addDay(), 'access_request_id' => 1]);
        $this->assertTrue($access->decide($user, 'storeops.documents.post')->allowed);
        DB::table('gov_access_grants')->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($access->decide($user, 'storeops.documents.post')->allowed);
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $this->assertTrue($access->decide($user, 'storeops.documents.post')->allowed);
        DB::table('gov_office_responsibilities')->delete();
        $this->assertFalse($access->decide($user, 'storeops.documents.post')->allowed);
    }

    public function test_catalog_review_is_bound_to_actor_metadata_files_and_expiry(): void
    {
        $user = $this->actor('superuser');
        $request = Request::create('/');
        $request->setUserResolver(fn () => $user);
        $session = app('session')->driver('array');
        $session->start();
        $request->setLaravelSession($session);
        $file = tempnam(sys_get_temp_dir(), 'g1_');
        file_put_contents($file, 'first');
        try {
            $service = new CatalogReview;
            $paths = ['nodes' => $file];
            $token = $service->create($request, $paths, 'UNSPSC', 'test');
            $request->merge(['catalog_review_token' => $token, 'scheme' => 'UNSPSC', 'version' => 'test']);
            file_put_contents($file, 'changed');
            $this->assertHttpStatus(422, fn () => $service->consume($request, $paths));
            file_put_contents($file, 'first');
            $request->merge(['version' => 'tampered']);
            $this->assertHttpStatus(422, fn () => $service->consume($request, $paths));
            $request->merge(['version' => 'test']);
            $service->consume($request, $paths);
            $this->assertHttpStatus(422, fn () => $service->consume($request, $paths));
        } finally {
            unlink($file);
        }
    }

    public function test_new_language_strings_match_and_templates_compile(): void
    {
        $english = require base_path('packages/gov-store/tenant-scope/src/resources/lang/en-US/access.php');
        $bangla = require base_path('packages/gov-store/tenant-scope/src/resources/lang/bn-BD/access.php');
        $this->assertSame(array_keys($english), array_keys($bangla));
        $this->assertSame(array_keys($english['abilities']), array_keys($bangla['abilities']));
        foreach (array_keys(config('govstore-abilities')) as $ability) {
            $this->assertArrayHasKey(str_replace('.', '_', $ability), $english['abilities']);
        }
        $templates = array_merge(glob(base_path('packages/gov-store/tenant-scope/src/resources/views/access/*.blade.php')), [
            base_path('packages/gov-store/tenant-scope/src/resources/views/hooks/unified-menu.blade.php'),
            base_path('packages/gov-store/store-operations/src/resources/views/operations/workspace.blade.php'),
            base_path('packages/gov-store/store-operations/src/resources/views/operations/print.blade.php'),
            base_path('packages/gov-store/classification/src/resources/views/manager/import.blade.php'),
        ]);
        foreach ($templates as $file) {
            $compiled = app('blade.compiler')->compileString(file_get_contents($file));
            $temporary = tempnam(sys_get_temp_dir(), 'g1_blade_');
            try {
                file_put_contents($temporary, $compiled);
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

    public function test_office_initialization_preserves_service_references_and_clears_global_flags(): void
    {
        $user = $this->actor();
        $user->location_id = 10;
        $user->company_id = 20;
        $context = app(TenantContext::class);
        $context->isGlobal = true;
        $context->isCompanyAdmin = true;
        $context->configs = ['stale' => true];
        $resolver = Mockery::mock(AssignmentResolver::class);
        $resolver->shouldReceive('resolveActiveRole')->with(1, 10)->once()->andReturn('employee');
        $capabilities = Mockery::mock(CapabilityProfileResolver::class);
        $permissions = new EffectivePermissionSet([]);
        $capabilities->shouldReceive('resolveSchema')->with('employee')->once()->andReturn($permissions);
        $adapter = Mockery::mock(SnipePermissionAdapter::class);
        $adapter->shouldReceive('adaptAndInject')->with($user, $permissions)->once();
        $middleware = new InitializeTenantContext($resolver, $capabilities, $adapter);
        $middleware->handle(Request::create('/'), fn () => response('ok'));
        $this->assertSame($context, app(TenantContext::class));
        $this->assertSame(10, $context->locationId);
        $this->assertSame(20, $context->companyId);
        $this->assertFalse($context->isGlobal);
        $this->assertFalse($context->isCompanyAdmin);
        $this->assertSame([], $context->configs);
    }

    public function test_rendered_failure_rolls_back_mutation_but_retains_failure_audit(): void
    {
        $user = $this->actor('storekeeper');
        $request = Request::create('/documents/test/draft', 'POST');
        $request->setUserResolver(fn () => $user);
        app()->instance('request', $request);
        $response = app(RequireGovAbility::class)->handle($request, function () {
            DB::table('users')->insert(['id' => 99, 'first_name' => 'Must roll back']);

            return response()->json(['error' => 'Rendered error'], 500);
        }, 'storeops.documents.draft');
        $this->assertSame(500, $response->getStatusCode());
        $this->assertFalse(DB::table('users')->where('id', 99)->exists());
        $this->assertSame(1, DB::table('gov_access_events')->where('outcome', 'failed')->count());
    }

    public function test_previous_page_error_does_not_roll_back_a_successful_action(): void
    {
        $user = $this->actor('storekeeper');
        $request = Request::create('/documents/test/draft', 'POST');
        $request->setUserResolver(fn () => $user);
        $session = app('session')->driver('array');
        $session->put(['error' => 'Previous page error', '_flash.old' => ['error'], '_flash.new' => []]);
        $request->setLaravelSession($session);
        app()->instance('request', $request);
        $response = app(RequireGovAbility::class)->handle($request, function () {
            DB::table('users')->insert(['id' => 99, 'first_name' => 'Successful action']);

            return response()->json(['ok' => true]);
        }, 'storeops.documents.draft');
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue(DB::table('users')->where('id', 99)->exists());
        $this->assertSame(1, DB::table('gov_access_events')->where('outcome', 'allowed')->count());
    }

    public function test_access_approval_grants_cover_without_logout_and_cannot_be_replayed(): void
    {
        Schema::create('gov_office_memberships', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id');
            $table->integer('location_id');
            $table->string('status');
        });
        Schema::create('gov_organization_activity_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id');
            $table->integer('performed_by');
            $table->string('event_type');
            $table->text('details');
        });
        DB::table('users')->insert(['id' => 1, 'location_id' => 10]);
        $employee = $this->actor();
        $access = app(GovAccess::class);
        $this->assertFalse($access->decide($employee, 'storeops.documents.post')->allowed);
        $id = DB::table('gov_access_requests')->insertGetId(['user_id' => 1, 'location_id' => 10,
            'ability' => 'storeops.documents.post', 'reason' => 'Temporary office cover', 'expires_at' => now()->addDay()]);
        $admin = $this->actor('office_admin', 2);
        $request = Request::create('/gov-store/access/requests/'.$id, 'POST', ['decision' => 'approved']);
        $request->setUserResolver(fn () => $admin);
        $request->setLaravelSession(app('session')->driver('array'));
        app()->instance('request', $request);
        $controller = app(AccessController::class);
        $service = app(RoleAssignmentService::class);
        $controller->review($request, $id, app(TenantContext::class), $service);
        $this->assertTrue($access->decide($employee, 'storeops.documents.post')->allowed);
        $this->assertSame('approved', DB::table('gov_access_requests')->where('id', $id)->value('status'));
        $this->assertSame(1, DB::table('gov_access_notices')->where('user_id', 1)->count());
        $this->assertHttpStatus(409, fn () => $controller->review($request, $id, app(TenantContext::class), $service));
        DB::table('gov_access_grants')->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($access->decide($employee, 'storeops.documents.post')->allowed);
    }

    public function test_national_review_freezes_input_rejects_stale_configuration_and_replay(): void
    {
        foreach (['locations', 'categories', 'models'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->increments('id');
                $table->timestamp('deleted_at')->nullable();
            });
        }
        Schema::create('gov_catalog_snipe_mappings', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->integer('category_id');
        });
        DB::table('gov_catalog_snipe_mappings')->insert(['code' => '123', 'category_id' => 1]);
        $user = $this->actor('superuser');
        $request = Request::create('/admin/catalog/mapping/save', 'POST', ['code' => '123', 'category_id' => 2]);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => Route::getRoutes()->getByName('gov.catalog.mapping.save'));
        $request->setLaravelSession(app('session')->driver('array'));
        $service = app(NationalChangeReview::class);
        $review = $service->handle($request, 'catalog.master.manage');
        $this->assertSame(409, $review->getStatusCode());
        $token = basename($review->getData(true)['review_url']);
        DB::table('gov_catalog_snipe_mappings')->update(['category_id' => 3]);
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Test national change']);
        $this->assertHttpStatus(409, fn () => $service->handle($request, 'catalog.master.manage'));
        DB::table('gov_catalog_snipe_mappings')->update(['category_id' => 1]);
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Test national change', 'code' => 'tampered', 'category_id' => 999]);
        $this->assertNull($service->handle($request, 'catalog.master.manage'));
        $this->assertSame('123', $request->input('code'));
        $this->assertSame(2, $request->input('category_id'));
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Test national change']);
        $this->assertHttpStatus(422, fn () => $service->handle($request, 'catalog.master.manage'));
    }

    public function test_request_approval_and_fulfillment_cannot_mutate_another_office(): void
    {
        Schema::create('custom_service_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('office_id');
            $table->timestamp('deleted_at')->nullable();
        });
        DB::table('custom_service_requests')->insert(['id' => 1, 'office_id' => 11]);
        $this->actor('primary_approver');
        $request = Request::create('/gov-requests/admin/1/process', 'POST');
        $approval = Mockery::mock(ApprovalService::class);
        $approval->shouldNotReceive('processDecision');
        $this->assertHttpStatus(404, fn () => app(GovApprovalController::class)->process($request, 1, $approval));
        $this->actor('storekeeper');
        $fulfillment = Mockery::mock(FulfillmentService::class);
        $fulfillment->shouldNotReceive('issueItems');
        $fulfillment->shouldNotReceive('forceClose');
        $controller = app(GovFulfillmentController::class);
        $this->assertHttpStatus(404, fn () => $controller->process($request, 1, $fulfillment));
        $this->assertHttpStatus(404, fn () => $controller->close($request, 1, $fulfillment));
    }

    public function test_ready_validation_uses_saved_quantity_and_serials_instead_of_submitted_edits(): void
    {
        $document = Mockery::mock(Document::class)->makePartial();
        $document->status = 'READY';
        $document->compiled_profile_snapshot = ['items' => ['consumable_1' => [
            'require_quantity' => ['enforced' => true], 'require_serial' => ['enforced' => true],
        ]]];
        $references = Mockery::mock(HasMany::class);
        $references->shouldReceive('where')->andReturnSelf();
        $references->shouldReceive('first')->andReturn(null);
        $document->shouldReceive('references')->andReturn($references);
        $item = new DocumentItem(['product_type' => 'consumable', 'product_id' => 1, 'quantity' => 2]);
        $item->setRelation('metadata', collect([
            new DocumentItemMeta(['field_key' => 'serial_number', 'row_index' => 0, 'value' => 'SERIAL-A']),
            new DocumentItemMeta(['field_key' => 'serial_number', 'row_index' => 1, 'value' => 'SERIAL-B']),
        ]));
        $item->setRelation('product', new Consumable(['name' => 'Test product']));
        $document->setRelation('items', collect([$item]));
        $submitted = ['items' => [['id' => 'consumable_1', 'qty' => 0, 'meta' => []]]];
        $service = app(DocumentValidationService::class);
        $this->assertSame([], $service->validateDocument($document, $submitted));
        $document->status = 'DRAFT';
        $this->assertNotEmpty($service->validateDocument($document, $submitted));
    }
}
