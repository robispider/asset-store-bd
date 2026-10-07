<?php

namespace Tests\Feature\GovStore;

use App\Exceptions\Handler;
use App\Models\Location;
use App\Models\User;
use GovStore\OfficeMembership\Http\Controllers\MembershipController;
use GovStore\OfficeMembership\Http\Middleware\SetWorkingContext;
use GovStore\OfficeMembership\Models\RoleAssignment;
use GovStore\OfficeMembership\Models\RoleHandshake;
use GovStore\OfficeMembership\Rules\NoActiveAssetsRule;
use GovStore\OfficeMembership\Services\ClearanceEngine;
use GovStore\OfficeMembership\Services\MembershipNotices;
use GovStore\OfficeMembership\Services\MembershipWorkflow;
use GovStore\OfficeMembership\Services\RoleAssignmentService;
use GovStore\OfficeMembership\Services\RoleHandshakeService;
use GovStore\OfficeMembership\Services\RoleTransfer;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\NationalChangeReview;
use GovStore\TenantScope\Services\SchemaKnowledge;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Monolog\Handler\NullHandler;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Every schema and write in this class is isolated in SQLite memory. */
class OfficeMembershipWorkflowTest extends TestCase
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
            'logging.default' => 'testing', 'logging.channels.testing' => ['driver' => 'monolog', 'handler' => NullHandler::class],
            'starter_templates.office_types.default' => ['Office Furniture', 'Basic Stationery']]);
        DB::purge('sqlite');
        app(SchemaKnowledge::class)->clear();
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('first_name')->default('Test');
            $t->string('last_name')->nullable();
            $t->string('username')->nullable();
            $t->string('email')->nullable();
            $t->string('jobtitle')->nullable();
            $t->integer('company_id')->nullable();
            $t->integer('location_id')->nullable();
            $t->boolean('activated')->default(true);
            $t->text('permissions')->nullable();
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
        Schema::create('companies', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('locations', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->integer('company_id')->nullable();
            $t->integer('parent_id')->nullable();
            $t->integer('manager_id')->nullable();
            $t->string('city')->nullable();
            $t->string('state')->nullable();
            $t->string('country')->nullable();
            $t->string('currency')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('action_logs', function (Blueprint $t) {
            $t->increments('id');
            $t->string('item_type');
            $t->integer('item_id');
            $t->integer('created_by')->nullable();
            $t->string('action_type');
            $t->string('remote_ip')->nullable();
            $t->string('user_agent')->nullable();
            $t->string('action_source')->nullable();
            $t->integer('company_id')->nullable();
            $t->text('log_meta')->nullable();
            $t->timestamp('action_date')->nullable();
            $t->timestamps();
        });
        Schema::create('gov_geo_areas', function (Blueprint $t) {
            $t->integer('GeoAreaId')->primary();
            $t->string('hid');
            $t->integer('geo_code');
            $t->string('geo_type');
            $t->string('en_name');
            $t->string('bn_name');
            $t->integer('GeoLevel')->default(1);
        });
        require_once base_path('packages/gov-store/organization/src/database/migrations/2024_01_04_000000_create_gov_organization_tables.php');
        (new \CreateGovOrganizationTables)->up();
        Schema::table('gov_location_profiles', fn (Blueprint $t) => $t->string('office_type')->default('default'));
        Schema::create('gov_office_memberships', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('location_id');
            $t->string('status');
            $t->boolean('is_home_office')->default(true);
            $t->date('valid_until')->nullable();
            $t->timestamps();
        });
        Schema::create('gov_office_responsibilities', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('location_id');
            $t->string('role_slug');
            $t->timestamps();
        });
        Schema::create('gov_company_admins', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('company_id');
        });
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        Schema::create('categories', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('category_type');
            $t->boolean('checkin_email')->default(false);
            $t->boolean('require_acceptance')->default(false);
            $t->boolean('use_default_eula')->default(false);
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('models', function (Blueprint $t) {
            $t->increments('id');
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('gov_tenant_scope_mappings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('reference_type');
            $t->integer('reference_id');
            $t->string('scope_type');
            $t->integer('scope_id');
            $t->boolean('is_active')->default(true);
            $t->timestamps();
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
        $this->membershipSchema();
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

    private function membershipSchema(): void
    {
        Schema::create('gov_employee_verification_tokens', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->string('token');
            $t->timestamp('expires_at');
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
        Schema::table('gov_office_memberships', function (Blueprint $t) {
            $t->integer('approved_by_user_id')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->string('approval_note')->nullable();
        });
        Schema::table('gov_location_profiles', function (Blueprint $t) {
            $t->string('invitation_code')->nullable();
            $t->timestamp('invitation_code_expires_at')->nullable();
        });
        Schema::create('gov_role_handshakes', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('location_id');
            $t->string('role_type');
            $t->integer('incoming_user_id');
            $t->integer('outgoing_user_id');
            $t->string('status');
            $t->timestamps();
        });
        (require base_path('packages/gov-store/office-membership/src/database/migrations/2026_10_08_000002_create_membership_notices.php'))->up();
        Schema::create('assets', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('location_id')->nullable();
            $t->integer('rtd_location_id')->nullable();
            $t->integer('assigned_to')->nullable();
            $t->string('assigned_type')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        foreach (['accessories', 'components', 'consumables'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->increments('id');
                $t->integer('location_id');
                $t->timestamp('deleted_at')->nullable();
            });
        }
        Schema::create('accessories_checkout', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('accessory_id');
            $t->integer('assigned_to');
            $t->string('assigned_type');
        });
        Schema::create('components_assets', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('component_id');
            $t->integer('asset_id');
            $t->integer('assigned_qty');
        });
        Schema::create('consumables_users', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('consumable_id');
            $t->integer('assigned_to');
        });
        Schema::create('licenses', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('company_id')->nullable();
        });
        Schema::create('license_seats', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('license_id');
            $t->integer('assigned_to')->nullable();
            $t->integer('asset_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('custom_service_requests', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('requested_by');
            $t->integer('office_id')->nullable();
            $t->string('approval_status');
            $t->string('fulfillment_status');
            $t->timestamp('deleted_at')->nullable();
            $t->timestamp('return_requested_at')->nullable();
            $t->integer('return_document_id')->nullable();
        });
        Schema::create('gov_documents', function (Blueprint $t) {
            $t->increments('id');
            $t->string('status');
            $t->string('type')->default('receipt');
            $t->integer('location_id')->default(10);
        });
        Schema::create('gov_committees', function (Blueprint $t) {
            $t->increments('id');
            $t->string('status');
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('gov_committee_tenures', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('home_location_id_snapshot');
            $t->integer('corrects_tenure_id')->nullable();
            $t->date('from_date');
            $t->date('to_date')->nullable();
            $t->integer('committee_id');
        });
        DB::table('gov_office_responsibilities')->insert(['user_id' => 2, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $this->actor(2);
    }

    private function failure(callable $action, int $status): void
    {
        try {
            $action();
            $this->fail('Forbidden operation succeeded');
        } catch (ModelNotFoundException $e) {
            $this->assertSame(404, $status);
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_both_transfer_paths_enforce_live_owner_membership_and_participant_state(): void
    {
        foreach ([RoleHandshake::class, RoleAssignment::class] as $type) {
            $service = app(RoleTransfer::class);
            $this->actor(3);
            $this->failure(fn () => $service->propose($type, 10, 'storekeeper', 2, 3), 403);
            $this->failure(fn () => $service->propose($type, 10, 'storekeeper', 3, 2), 409);
            $this->actor(2);
            $this->failure(fn () => $service->propose($type, 30, 'storekeeper', 2, 4), 404);
            $this->failure(fn () => $service->propose($type, 10, 'superuser', 2, 3), 422);
            $this->failure(fn () => $service->propose($type, 10, 'storekeeper', 2, 2), 422);
            DB::table('gov_office_memberships')->where('user_id', 3)->update(['valid_until' => today()->subDay()]);
            $this->failure(fn () => $service->propose($type, 10, 'storekeeper', 2, 3), 404);
            DB::table('gov_office_memberships')->where('user_id', 3)->update(['valid_until' => null]);
            $proposal = $service->propose($type, 10, 'storekeeper', 2, 3);
            $this->assertSame(2, DB::table('gov_membership_notices')->count());
            $this->failure(fn () => $service->propose($type, 10, 'storekeeper', 2, 3), 409);
            $this->failure(fn () => $service->transition($type, $proposal->id, 2, 'accept'), 404);
            $this->actor(3);
            DB::table('gov_office_responsibilities')->where('role_slug', 'storekeeper')->delete();
            $this->failure(fn () => $service->transition($type, $proposal->id, 3, 'accept'), 409);
            $this->assertSame('pending', $proposal->fresh()->status);
            DB::table('gov_office_responsibilities')->insert(['user_id' => 2, 'location_id' => 10, 'role_slug' => 'storekeeper']);
            DB::table('gov_office_memberships')->where('user_id', 3)->update(['status' => 'inactive']);
            $this->failure(fn () => $service->transition($type, $proposal->id, 3, 'accept'), 404);
            DB::table('gov_office_memberships')->where('user_id', 3)->update(['status' => 'active']);
            $service->transition($type, $proposal->id, 3, 'accept');
            $this->assertSame(3, (int) DB::table('gov_office_responsibilities')->where('role_slug', 'storekeeper')->value('user_id'));
            $this->failure(fn () => $service->transition($type, $proposal->id, 3, 'accept'), 409);
            $this->assertSame(4, DB::table('gov_membership_notices')->count());
            DB::table('gov_membership_notices')->delete();
            DB::table('gov_office_responsibilities')->where('role_slug', 'storekeeper')->update(['user_id' => 2]);
        }
    }

    public function test_office_admin_handover_and_cancel_preserve_history_and_reject_replay(): void
    {
        $service = app(RoleHandshakeService::class);
        $proposal = $service->proposeHandshake(10, 'office_admin', 2, 3);
        $this->actor(3);
        $service->acceptHandshake($proposal->id, 3);
        $this->assertSame(3, (int) DB::table('gov_location_profiles')->where('location_id', 10)->value('office_admin_id'));
        $this->actor(2);
        $proposal = $service->proposeHandshake(10, 'storekeeper', 2, 3);
        $this->actor(3);
        $this->failure(fn () => $service->cancelHandshake($proposal->id, 3), 404);
        $service->rejectHandshake($proposal->id, 3);
        $this->failure(fn () => $service->rejectHandshake($proposal->id, 3), 409);
        $this->actor(2);
        $proposal = app(RoleAssignmentService::class)->proposeTransfer(10, 'storekeeper', 2, 3);
        app(RoleAssignmentService::class)->cancelTransfer($proposal->id, 2);
        $this->assertSame('cancelled', $proposal->fresh()->status);
    }

    public function test_all_native_holdings_are_office_bounded_and_consumption_history_is_preserved(): void
    {
        $rule = new NoActiveAssetsRule;
        $user = $this->actor(3);
        DB::table('assets')->insert(['id' => 1, 'location_id' => 30, 'rtd_location_id' => 30, 'assigned_type' => User::class, 'assigned_to' => 3]);
        // Numeric collisions with a location assignment must never count as user custody.
        DB::table('assets')->insert(['id' => 2, 'location_id' => 10, 'assigned_type' => Location::class, 'assigned_to' => 3]);
        $this->assertTrue($rule->check($user, 10)->isPassed);
        DB::table('assets')->where('id', 1)->update(['rtd_location_id' => 10]);
        $this->assertFalse($rule->check($user, 10)->isPassed);
        DB::table('assets')->where('id', 1)->update(['rtd_location_id' => 30]);
        DB::table('accessories')->insert(['id' => 1, 'location_id' => 10]);
        DB::table('accessories_checkout')->insert(['accessory_id' => 1, 'assigned_type' => User::class, 'assigned_to' => 3]);
        $this->assertFalse($rule->check($user, 10)->isPassed);
        DB::table('accessories_checkout')->delete();
        DB::table('components')->insert(['id' => 1, 'location_id' => 10]);
        DB::table('components_assets')->insert(['component_id' => 1, 'asset_id' => 1, 'assigned_qty' => 1]);
        $this->assertFalse($rule->check($user, 10)->isPassed);
        DB::table('components_assets')->update(['assigned_qty' => 0]);
        $this->assertTrue($rule->check($user, 10)->isPassed);
        DB::table('licenses')->insert(['id' => 1, 'company_id' => 20]);
        DB::table('license_seats')->insert(['license_id' => 1, 'assigned_to' => 3]);
        $this->assertFalse($rule->check($user, 10)->isPassed);
        DB::table('license_seats')->update(['assigned_to' => null]);
        DB::table('consumables')->insert(['id' => 1, 'location_id' => 10]);
        DB::table('consumables_users')->insert(['consumable_id' => 1, 'assigned_to' => 3]);
        $this->assertTrue($rule->check($user, 10)->isPassed);
        $this->assertSame(1, DB::table('consumables_users')->count());
    }

    public function test_request_and_return_obligations_block_release_through_consumer_registration(): void
    {
        $user = $this->actor(3);
        $engine = app(ClearanceEngine::class);
        $this->assertGreaterThanOrEqual(4, count($engine->runChecks($user, 10)));
        DB::table('custom_service_requests')->insert(['requested_by' => 3, 'office_id' => 10, 'approval_status' => 'pending_primary', 'fulfillment_status' => 'pending']);
        $this->assertFalse($engine->isCleared($engine->runChecks($user, 10)));
        DB::table('custom_service_requests')->update(['approval_status' => 'approved', 'fulfillment_status' => 'issued', 'return_requested_at' => now()]);
        $this->assertFalse($engine->isCleared($engine->runChecks($user, 10)));
        DB::table('gov_documents')->insert(['id' => 1, 'status' => 'POSTED']);
        DB::table('custom_service_requests')->update(['return_document_id' => 1]);
        app(TenantContext::class)->locationId = 30;
        $this->assertTrue($engine->isCleared($engine->runChecks($user, 10)));
        DB::table('gov_documents')->update(['location_id' => 30]);
        $this->assertFalse($engine->isCleared($engine->runChecks($user, 10)));
    }

    public function test_release_signoff_claim_rechecks_clearance_and_never_claims_arbitrary_users(): void
    {
        $workflow = app(MembershipWorkflow::class);
        $this->actor(3);
        $id = DB::table('gov_office_memberships')->where('user_id', 3)->value('id');
        $workflow->requestRelease($id);
        $this->failure(fn () => $workflow->requestRelease($id), 404);
        $this->actor(2);
        $workflow->decide($id, 'release');
        $this->failure(fn () => $workflow->decide($id, 'release'), 409);
        DB::table('locations')->insert(['id' => 50, 'name' => 'Receiving office', 'company_id' => 20]);
        DB::table('gov_location_profiles')->insert(['location_id' => 50, 'geo_area_id' => 2, 'office_admin_id' => 2, 'lifecycle_status' => 'configured']);
        DB::table('gov_office_memberships')->insert(['user_id' => 2, 'location_id' => 50, 'status' => 'active']);
        app(TenantContext::class)->locationId = 50;
        $this->failure(fn () => $workflow->claim(4), 409);
        DB::table('assets')->insert(['location_id' => 10, 'assigned_type' => User::class, 'assigned_to' => 3]);
        $this->failure(fn () => $workflow->claim(3), 409);
        $this->assertSame(10, (int) DB::table('users')->where('id', 3)->value('location_id'));
        DB::table('assets')->delete();
        $workflow->claim(3);
        $this->assertSame(50, (int) DB::table('users')->where('id', 3)->value('location_id'));
        $this->assertSame(1, DB::table('gov_office_memberships')->where('user_id', 3)->where('is_home_office', true)->count());
        $this->failure(fn () => $workflow->claim(3), 409);
    }

    public function test_clearance_includes_admin_and_temporary_cover_and_notices_roll_back(): void
    {
        $engine = app(ClearanceEngine::class);
        $this->assertFalse($engine->isCleared($engine->runChecks($this->actor(2), 10)));
        $user = $this->actor(3);
        DB::table('gov_access_grants')->insert(['user_id' => 3, 'location_id' => 10, 'role_slug' => 'storekeeper', 'expires_at' => now()->addDay(), 'access_request_id' => 1]);
        $this->assertFalse($engine->isCleared($engine->runChecks($user, 10)));
        DB::table('gov_access_grants')->update(['expires_at' => now()->subMinute()]);
        $this->assertTrue($engine->isCleared($engine->runChecks($user, 10)));
        DB::beginTransaction();
        app(MembershipNotices::class)->record('transfer_proposed', 10, [2, 3]);
        DB::rollBack();
        $this->assertSame(0, DB::table('gov_membership_notices')->count());
        $this->artisan('govstore:membership-mail')->assertSuccessful();
    }

    public function test_package_routes_have_explicit_abilities_and_bilingual_templates_compile(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->getName() ?? '', 'gov.membership.')) {
                continue;
            }
            $abilities = array_filter($route->gatherMiddleware(), fn ($m) => str_starts_with($m, 'gov.can:'));
            $this->assertCount(1, $abilities, $route->uri());
            $this->assertTrue(method_exists($route->getControllerClass(), $route->getActionMethod()));
        }
        $en = require base_path('packages/gov-store/office-membership/src/resources/lang/en-US/member.php');
        $bn = require base_path('packages/gov-store/office-membership/src/resources/lang/bn-BD/member.php');
        $this->assertSame(array_keys($en), array_keys($bn));
        foreach (['user.index', 'admin.staff', 'admin.override_console', 'hooks.membership-menu', 'hooks.notices'] as $view) {
            $source = app('view')->getFinder()->find('govmem::'.$view);
            $compiled = app('blade.compiler')->compileString(file_get_contents($source));
            $temp = tempnam(sys_get_temp_dir(), 'membership-blade-');
            file_put_contents($temp, $compiled);
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1', $output, $status);
            unlink($temp);
            $this->assertSame(0, $status, implode("\n", $output));
        }
    }

    public function test_join_review_and_token_consumption_are_atomic_and_replay_guarded(): void
    {
        $workflow = app(MembershipWorkflow::class);
        DB::table('users')->where('id', 4)->update(['company_id' => 20, 'username' => 'test-joining']);
        DB::table('gov_location_profiles')->where('location_id', 10)->update(['invitation_code' => 'JOINTEST', 'invitation_code_expires_at' => now()->addHour()]);
        $this->actor(4);
        $workflow->join('jointest');
        $this->failure(fn () => $workflow->join('JOINTEST'), 409);
        $id = DB::table('gov_office_memberships')->where('user_id', 4)->where('location_id', 10)->value('id');
        $this->failure(fn () => $workflow->decide($id, 'approve'), 404);
        $this->actor(2);
        $workflow->decide($id, 'reject');
        $this->assertSame('rejected', DB::table('gov_office_memberships')->where('id', $id)->value('status'));
        $this->actor(4);
        $workflow->join('JOINTEST');
        $this->actor(2);
        $workflow->decide($id, 'approve');
        $this->assertFalse((bool) DB::table('gov_office_memberships')->where('id', $id)->value('is_home_office'));
        $this->failure(fn () => $workflow->decide($id, 'approve'), 409);
        DB::table('gov_office_memberships')->where('id', $id)->update(['status' => 'inactive']);
        DB::table('gov_employee_verification_tokens')->insert(['user_id' => 4, 'token' => 'TOKEN1', 'expires_at' => now()->addHour()]);
        $workflow->addByToken('test-joining', 'TOKEN1');
        $this->failure(fn () => $workflow->addByToken('test-joining', 'TOKEN1'), 422);
        $this->assertNotNull(DB::table('gov_employee_verification_tokens')->value('used_at'));
        $this->assertNotEmpty(app(MembershipNotices::class)->forUser(4));
        $this->assertEmpty(app(MembershipNotices::class)->forUser(1));
    }

    public function test_secondary_context_never_rewrites_home_and_expired_selection_is_refused(): void
    {
        $user = $this->actor(3);
        DB::table('gov_office_memberships')->where('user_id', 3)->update(['valid_until' => today()->subDay()]);
        $this->failure(fn () => app(MembershipController::class)
            ->switchContext(Request::create('/', 'POST', ['membership_id' => DB::table('gov_office_memberships')->where('user_id', 3)->value('id')])), 404);
        DB::table('gov_office_memberships')->insert(['user_id' => 3, 'location_id' => 30, 'status' => 'active', 'is_home_office' => false]);
        session()->forget('gov_working_membership_id');
        app(SetWorkingContext::class)->handle(Request::create('/'), fn () => response('ok'));
        $this->assertNotNull(session('gov_working_membership_id'));
        $this->assertSame(10, (int) $user->fresh()->location_id);
    }

    public function test_safe_http_failures_preserve_status_and_do_not_expose_exception_detail(): void
    {
        $request = Request::create('/gov-store/my-memberships/handshake/propose', 'POST');
        $request->headers->set('Accept', 'application/json');
        $route = Route::getRoutes()->getByName('gov.membership.handshake.propose');
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => auth()->user());
        $response = app(Handler::class)->render($request, new \RuntimeException('SQL secret internal path'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('SQL secret', $response->getContent());
        $this->assertArrayHasKey('reference_id', $response->getData(true));
        $response = app(Handler::class)->render($request, new HttpException(409));
        $this->assertSame(409, $response->getStatusCode());
    }

    public function test_historical_membership_migration_preserves_installed_data_on_rerun(): void
    {
        Schema::create('gov_override_audit_logs', fn (Blueprint $t) => $t->increments('id'));
        $before = DB::table('gov_office_memberships')->count();
        require_once base_path('packages/gov-store/office-membership/src/database/migrations/2024_01_07_000000_create_gov_office_membership_tables.php');
        (new \CreateGovOfficeMembershipTables)->up();
        $this->assertSame($before, DB::table('gov_office_memberships')->count());
        $this->assertTrue(Schema::hasTable('gov_role_assignments'));
    }

    public function test_override_review_binds_target_snapshot_reason_confirmation_and_replay(): void
    {
        $user = $this->actor(1);
        $request = Request::create('/gov-store/admin/memberships/override/force', 'POST', ['user_id' => 3, 'override_type' => 'strip_roles', 'reason' => 'Isolated reviewed override']);
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => $user);
        $request->setRouteResolver(fn () => Route::getRoutes()->getByName('gov.membership.override'));
        $request->setLaravelSession(app('session')->driver('array'));
        $service = app(NationalChangeReview::class);
        $review = $service->handle($request, 'membership.override');
        $this->assertSame(409, $review->getStatusCode());
        $token = basename($review->getData(true)['review_url']);
        DB::table('gov_office_memberships')->where('user_id', 3)->update(['status' => 'inactive']);
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Reviewed test change']);
        $this->failure(fn () => $service->handle($request, 'membership.override'), 409);
        DB::table('gov_office_memberships')->where('user_id', 3)->update(['status' => 'active']);
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Reviewed test change', 'user_id' => 4]);
        $this->assertNull($service->handle($request, 'membership.override'));
        $this->assertSame(3, $request->input('user_id'));
        $this->assertSame('strip_roles', $request->input('override_type'));
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Reviewed test change']);
        $this->failure(fn () => $service->handle($request, 'membership.override'), 422);
    }

    public function test_optional_mail_uses_committed_outbox_and_preserves_notice_on_failure(): void
    {
        config(['membership-notices.mail_enabled' => true]);
        DB::table('users')->where('id', 3)->update(['email' => 'fixture@example.test']);
        app(MembershipNotices::class)->record('transfer_proposed', 10, [3]);
        Mail::shouldReceive('raw')->once()->andThrow(new \RuntimeException('Test delivery unavailable'));
        $this->artisan('govstore:membership-mail')->assertFailed();
        $this->assertNull(DB::table('gov_membership_notices')->value('mailed_at'));
        $this->assertSame(1, DB::table('gov_membership_notices')->count());
        Mail::shouldReceive('raw')->once()->andReturnNull();
        $this->artisan('govstore:membership-mail')->assertSuccessful();
        $this->assertNotNull(DB::table('gov_membership_notices')->value('mailed_at'));
    }

    public function test_notice_failure_rolls_back_transfer_role_status_and_success_audit(): void
    {
        $proposal = app(RoleHandshakeService::class)->proposeHandshake(10, 'storekeeper', 2, 3);
        $this->actor(3);
        $notices = Mockery::mock(MembershipNotices::class);
        $notices->shouldReceive('record')->once()->andThrow(new \RuntimeException('Isolated outbox failure'));
        app()->instance(MembershipNotices::class, $notices);
        try {
            app(RoleHandshakeService::class)->acceptHandshake($proposal->id, 3);
            $this->fail('A failed outbox cannot commit a role change');
        } catch (\RuntimeException $e) {
            $this->assertSame('Isolated outbox failure', $e->getMessage());
        }
        $this->assertSame('pending', $proposal->fresh()->status);
        $this->assertSame(2, (int) DB::table('gov_office_responsibilities')->where('role_slug', 'storekeeper')->value('user_id'));
        $this->assertSame(0, DB::table('gov_organization_activity_logs')->count());
        $this->assertSame(2, DB::table('gov_membership_notices')->count());
    }
}
