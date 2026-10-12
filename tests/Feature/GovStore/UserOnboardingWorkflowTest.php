<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\SchemaKnowledge;
use GovStore\UserOnboarding\Http\Controllers\UserOnboardingController;
use GovStore\UserOnboarding\Models\UserOnboarding;
use GovStore\UserOnboarding\Services\OnboardingAccess;
use GovStore\UserOnboarding\Services\UserOnboardingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Monolog\Handler\NullHandler;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** All fixtures and migrations run only in SQLite memory. */
class UserOnboardingWorkflowTest extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'govstore-access.mode' => 'shadow',
            'logging.default' => 'testing', 'logging.channels.testing' => ['driver' => 'monolog', 'handler' => NullHandler::class]]);
        DB::purge('sqlite');
        app(SchemaKnowledge::class)->clear();
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('first_name')->default('Test');
            $t->string('last_name')->nullable();
            $t->string('username')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->default('');
            $t->integer('company_id')->nullable();
            $t->integer('location_id')->nullable();
            $t->boolean('activated')->default(true);
            $t->text('permissions')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('companies', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('company_user', function (Blueprint $t) {
            $t->integer('user_id');
            $t->integer('company_id');
            $t->unique(['user_id', 'company_id']);
        });
        Schema::create('permission_groups', function (Blueprint $t) {
            $t->increments('id');
            $t->text('permissions')->nullable();
        });
        Schema::create('users_groups', function (Blueprint $t) {
            $t->integer('user_id');
            $t->integer('group_id');
        });
        Schema::create('locations', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->integer('company_id')->nullable();
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('gov_geo_areas', function (Blueprint $t) {
            $t->unsignedInteger('GeoAreaId')->primary();
            $t->string('hid');
            $t->string('en_name');
        });
        require_once base_path('packages/gov-store/organization/src/database/migrations/2024_01_04_000000_create_gov_organization_tables.php');
        (new \CreateGovOrganizationTables)->up();
        require_once base_path('packages/gov-store/office-membership/src/database/migrations/2024_01_07_000000_create_gov_office_membership_tables.php');
        (new \CreateGovOfficeMembershipTables)->up();
        Schema::create('gov_company_admins', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('company_id');
        });
        Schema::create('gov_office_responsibilities', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('location_id');
            $t->string('role_slug');
        });
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
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
            $t->timestamp('action_date')->nullable();
            $t->timestamps();
        });
        DB::table('companies')->insert([['id' => 20, 'name' => 'A'], ['id' => 40, 'name' => 'B']]);
        foreach ([1 => 'superuser', 2 => 'office', 3 => 'company', 4 => 'ict', 5 => 'admin', 6 => 'other', 7 => 'company'] as $id => $role) {
            DB::table('users')->insert(['id' => $id, 'first_name' => 'Actor '.$id, 'company_id' => $id === 6 ? 40 : 20, 'location_id' => $id === 2 ? 10 : null, 'permissions' => $role === 'superuser' ? '{"superuser":1}' : ($role === 'admin' ? '{"admin":1}' : '{}')]);
        }
        DB::table('users')->where('id', 1)->update(['permissions' => '{"superuser":1}']);
        DB::table('users')->where('id', 5)->update(['permissions' => '{"admin":1}']);
        DB::table('gov_geo_areas')->insert([['GeoAreaId' => 1, 'hid' => '/10/', 'en_name' => 'Division'], ['GeoAreaId' => 2, 'hid' => '/10/11/', 'en_name' => 'District'], ['GeoAreaId' => 3, 'hid' => '/20/', 'en_name' => 'Outside']]);
        DB::table('locations')->insert([['id' => 10, 'company_id' => 20, 'name' => 'A'], ['id' => 30, 'company_id' => 40, 'name' => 'B'], ['id' => 11, 'company_id' => 20, 'name' => 'Suspended']]);
        foreach ([10 => 2, 30 => 3, 11 => 2] as $id => $geo) {
            DB::table('gov_location_profiles')->insert(['location_id' => $id, 'geo_area_id' => $geo, 'office_admin_id' => $id === 10 ? 2 : null, 'lifecycle_status' => $id === 11 ? 'suspended' : 'configured']);
        }
        DB::table('gov_office_memberships')->insert(['user_id' => 2, 'location_id' => 10, 'is_home_office' => true, 'status' => 'active']);
        DB::table('gov_company_admins')->insert([['user_id' => 3, 'company_id' => 20], ['user_id' => 7, 'company_id' => 20], ['user_id' => 6, 'company_id' => 40]]);
        DB::table('gov_ict_jurisdictions')->insert(['user_id' => 4, 'geo_area_id' => 1]);
        (require base_path('packages/gov-store/user-onboarding/src/database/migrations/2024_02_10_000000_create_gov_user_onboardings_table.php'))->up();
        (require base_path('packages/gov-store/user-onboarding/src/database/migrations/2026_10_08_000003_add_onboarding_history_and_notices.php'))->up();
        $this->actor(1);
    }

    private function actor(?int $id): void
    {
        auth()->forgetGuards();
        app(TenantContext::class)->reset();
        if ($id) {
            $user = User::withoutGlobalScopes()->findOrFail($id);
            auth()->setUser($user);
            $c = app(TenantContext::class);
            $c->locationId = $user->location_id;
            $c->companyId = $user->company_id;
            $c->isCompanyAdmin = in_array($id, [3, 6, 7]);
        }
    }

    private function person(?int $company = 20): User
    {
        $user = new User;
        $user->forceFill(['first_name' => 'Fictional', 'username' => 'person-'.DB::table('users')->count(), 'password' => bcrypt('fixture-password'), 'company_id' => $company, 'activated' => false, 'permissions' => '{}']);
        $this->assertTrue($user->save());

        return $user;
    }

    private function failure(callable $call, int $status): void
    {
        try {
            $call();
            $this->fail('Expected failure');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($status, $e->getStatusCode());
        } catch (ModelNotFoundException $e) {
            $this->assertSame(404, $status);
        }
    }

    public function test_system_creation_queues_without_fabricated_creator_owner_or_office(): void
    {
        $this->actor(null);
        $user = $this->person(null);
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('SYSTEM', $item->owner_type);
        $this->assertNull($item->creator_user_id);
        $this->assertNull($item->owner_id);
        $this->assertNull($item->geo_area_id);
        $this->assertNull($user->location_id);
        $this->assertSame(0, DB::table('gov_office_memberships')->where('user_id', $user->id)->count());
        $this->assertSame(1, DB::table('gov_onboarding_notices')->where('user_id', $user->id)->count());
        $this->actor(3);
        $this->assertFalse(app(OnboardingAccess::class)->queue(auth()->user())->whereKey($item->id)->exists());
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 10), 404);
        $this->actor(1);
        app(UserOnboardingService::class)->assignToOffice($item->id, 10);
        $this->assertSame('COMPLETED', $item->fresh()->status);
        $this->assertSame(20, DB::table('users')->where('id', $user->id)->value('company_id'));
        $this->assertSame(1, DB::table('company_user')->where('user_id', $user->id)->where('company_id', 20)->count());
    }

    public function test_company_queue_and_assignment_are_owned_bounded_and_preserve_activation(): void
    {
        $this->actor(3);
        $user = $this->person();
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('COMPANY_ADMIN', $item->owner_type);
        $this->assertCount(1, app(OnboardingAccess::class)->queue(auth()->user())->get());
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 30), 404);
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 11), 409);
        $this->actor(7);
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 10), 404);
        $this->actor(3);
        app(UserOnboardingService::class)->assignToOffice($item->id, 10);
        $this->assertFalse((bool) DB::table('users')->where('id', $user->id)->value('activated'));
        $this->assertSame('{}', DB::table('users')->where('id', $user->id)->value('permissions'));
        $this->assertSame(10, DB::table('users')->where('id', $user->id)->value('location_id'));
        $this->assertSame(3, DB::table('gov_onboarding_notices')->where('event_key', 'assigned')->count());
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 10), 409);
        $this->assertSame(1, DB::table('gov_onboarding_events')->where('event_key', 'assigned')->count());
    }

    public function test_office_admin_creation_injects_consistent_home_and_refuses_foreign_input(): void
    {
        $this->actor(2);
        $user = $this->person(null);
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('COMPLETED', $item->status);
        $this->assertSame(10, $user->fresh()->location_id);
        $this->assertSame(20, $user->fresh()->company_id);
        $this->failure(fn () => $this->person(40), 404);
        DB::table('gov_location_profiles')->where('location_id', 10)->update(['lifecycle_status' => 'suspended']);
        $this->failure(fn () => $this->person(), 409);
    }

    public function test_ict_queue_and_targets_enforce_geography_and_revocation(): void
    {
        $this->actor(4);
        $user = $this->person(null);
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('ICT_OFFICER', $item->owner_type);
        $this->assertSame(1, (int) $item->geo_area_id);
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 30), 404);
        DB::table('gov_ict_jurisdictions')->where('user_id', 4)->delete();
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 10), 403);
        $this->assertSame('WAITING', $item->fresh()->status);
        DB::table('gov_ict_jurisdictions')->insert(['user_id' => 4, 'geo_area_id' => 1]);
        app(UserOnboardingService::class)->assignToOffice($item->id, 10);
        $this->assertSame('COMPLETED', $item->fresh()->status);
    }

    public function test_native_admin_and_staff_cannot_assign_even_in_shadow_mode(): void
    {
        $this->actor(1);
        $user = $this->person();
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        $this->actor(5);
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 10), 403);
        $this->actor($user->id);
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 10), 403);
        $this->assertSame('WAITING', $item->fresh()->status);
    }

    public function test_cancel_reject_reopen_and_reassign_preserve_reasons_and_reject_replay(): void
    {
        $this->actor(3);
        $user = $this->person();
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        $service = app(UserOnboardingService::class);
        $service->decide($item->id, 'reject', 'Duplicate account');
        $this->assertSame('CANCELLED', $item->fresh()->status);
        $this->failure(fn () => $service->decide($item->id, 'cancel', 'Duplicate account'), 409);
        $service->decide($item->id, 'reopen', 'Reconsidered request');
        $this->failure(fn () => $service->decide($item->id, 'reassign', 'Review by other authority', 6, 'COMPANY_ADMIN'), 404);
        $service->decide($item->id, 'reassign', 'Review by same organization', 7, 'COMPANY_ADMIN');
        $this->assertSame(7, (int) $item->fresh()->owner_id);
        $this->failure(fn () => $service->decide($item->id, 'cancel', 'Previous owner replay'), 404);
        $this->actor(7);
        $service->decide($item->id, 'cancel', 'No longer required');
        $this->assertSame(4, DB::table('gov_onboarding_events')->where('event_key', '!=', 'queued')->count());
        $this->assertSame('Duplicate account', DB::table('gov_onboarding_events')->where('event_key', 'reject')->value('reason'));
        $this->assertSame(1, DB::table('gov_onboarding_notices')->where('event_key', 'reassign')->where('user_id', 3)->count());
        $this->assertNull($user->fresh()->location_id);
    }

    public function test_existing_membership_cannot_be_rehomed_and_notice_failure_rolls_back_assignment(): void
    {
        $this->actor(3);
        $user = $this->person();
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        DB::table('gov_office_memberships')->insert(['user_id' => $user->id, 'location_id' => 30, 'status' => 'active', 'is_home_office' => true]);
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 10), 409);
        DB::table('gov_office_memberships')->where('user_id', $user->id)->delete();
        Schema::drop('gov_onboarding_notices');
        try {
            app(UserOnboardingService::class)->assignToOffice($item->id, 10);
            $this->fail('Notice outage expected');
        } catch (QueryException $e) {
        }
        $this->assertSame('WAITING', $item->fresh()->status);
        $this->assertNull($user->fresh()->location_id);
        $this->assertSame(0, DB::table('gov_office_memberships')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('gov_onboarding_events')->where('event_key', 'assigned')->count());
        $this->assertSame(0, DB::table('gov_organization_activity_logs')->where('details', 'like', '%target_user_id%'.$user->id.'%')->count());
    }

    public function test_routes_translation_keys_templates_and_personal_notices_are_private(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->getName() ?? '', 'gov.onboard.')) {
                continue;
            }
            $this->assertCount(1, array_filter($route->gatherMiddleware(), fn ($m) => str_starts_with($m, 'gov.can:')));
            $this->assertTrue(method_exists($route->getControllerClass(), $route->getActionMethod()));
        }
        $en = require base_path('packages/gov-store/user-onboarding/src/resources/lang/en-US/onboard.php');
        $bn = require base_path('packages/gov-store/user-onboarding/src/resources/lang/bn-BD/onboard.php');
        $this->assertSame(array_keys($en), array_keys($bn));
        foreach (['index', 'mine', 'notices'] as $name) {
            $source = app('view')->getFinder()->find('govonboard::queue.'.$name);
            $compiled = app('blade.compiler')->compileString(file_get_contents($source));
            $temp = tempnam(sys_get_temp_dir(), 'onboarding-blade-');
            file_put_contents($temp, $compiled);
            exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($temp).' 2>&1', $output, $status);
            unlink($temp);
            $this->assertSame(0, $status, implode("\n", $output));
        }
        $this->actor(3);
        $user = $this->person();
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        app(UserOnboardingService::class)->decide($item->id, 'reject', 'Private administrative reason');
        $this->actor($user->id);
        $view = app(UserOnboardingController::class)->mine();
        $this->assertCount(2, $view->getData()['notices']);
        $this->assertSame($item->id, $view->getData()['item']->id);
        $html = app('blade.compiler')->compileString(file_get_contents(app('view')->getFinder()->find('govonboard::queue.mine')));
        $this->assertStringNotContainsString('reason', $html);
    }

    public function test_foreign_creation_and_corrupt_queue_metadata_cannot_expand_manager_scope(): void
    {
        $this->actor(3);
        $this->failure(fn () => $this->person(40), 404);
        $user = $this->person();
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        DB::table('users')->where('id', $user->id)->update(['company_id' => 40]);
        $this->assertFalse(app(OnboardingAccess::class)->queue(auth()->user())->whereKey($item->id)->exists());
        $this->failure(fn () => app(UserOnboardingService::class)->assignToOffice($item->id, 10), 404);
        DB::table('gov_user_onboardings')->where('id', $item->id)->update(['owner_type' => 'ICT_OFFICER', 'owner_id' => 4, 'geo_area_id' => 3]);
        $this->actor(4);
        $this->assertFalse(app(OnboardingAccess::class)->queue(auth()->user())->whereKey($item->id)->exists());
        $this->actor(2);
        DB::table('gov_office_memberships')->where('user_id', 2)->update(['valid_until' => today()->subDay()]);
        $this->assertFalse(app(OnboardingAccess::class)->isOfficeAdmin(2, 10));
    }

    public function test_queue_forms_history_and_notices_render_without_exposing_raw_reason_html(): void
    {
        $this->actor(3);
        $user = $this->person();
        $item = UserOnboarding::where('user_id', $user->id)->firstOrFail();
        $controller = app(UserOnboardingController::class);
        $view = $controller->index(Request::create('/gov-store/admin/onboard'), app(OnboardingAccess::class));
        $this->assertArrayHasKey('COMPANY_ADMIN:7', $view->getData()['managers'][$item->id]);
        $this->assertSame([10], $view->getData()['locations']->pluck('id')->all());
        $this->assertCount(1, $view->getData()['queue']);
        $source = file_get_contents(app('view')->getFinder()->find('govonboard::queue.index'));
        $source = str_replace("@extends('layouts/default')", '', $source)."\n@yield('content')";
        $temp = tempnam(sys_get_temp_dir(), 'onboarding-render-');
        $blade = $temp.'.blade.php';
        file_put_contents($blade, $source);
        try {
            $html = app('view')->file($blade, $view->getData())->render();
            $this->assertStringContainsString('name="location_id"', $html);
            $this->assertStringContainsString('COMPANY_ADMIN:7', $html);
            app(UserOnboardingService::class)->decide($item->id, 'reject', '<script>private reason</script>');
            $view = $controller->index(Request::create('/gov-store/admin/onboard', 'GET', ['status' => 'CANCELLED']), app(OnboardingAccess::class));
            $html = app('view')->file($blade, $view->getData())->render();
            $this->assertStringContainsString('&lt;script&gt;private reason&lt;/script&gt;', $html);
            $this->assertStringNotContainsString('<script>private reason</script>', $html);
            $this->assertStringContainsString('value="reopen"', $html);
        } finally {
            unlink($blade);
            unlink($temp);
            $compiled = app('blade.compiler')->getCompiledPath($blade);
            if (is_file($compiled)) {
                unlink($compiled);
            }
        }
    }
}
