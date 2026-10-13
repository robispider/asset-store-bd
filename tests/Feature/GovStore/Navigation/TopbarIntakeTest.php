<?php

namespace Tests\Feature\GovStore\Navigation;

use App\Models\Asset;
use App\Models\Consumable;
use App\Models\User;
use GovStore\OfficeMembership\Services\OfficePicker;
use GovStore\StoreOperations\Http\Middleware\EnforceGrnIntake;
use GovStore\StoreOperations\Services\IntakePolicy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * docs/plans/topbar-navigation-plan.md — GRN-only intake (§4.6) and the navbar office picker
 * (§4.3). The intake guard works on route names, so super users are covered too.
 */
class TopbarIntakeTest extends NavigationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // In-memory only: these tests create tables and must never reach a real database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        config(['govstore-intake.mode' => 'grn_only', 'govstore-intake.block_licenses' => false]);
        Schema::create('gov_store_ledger_openings', function (Blueprint $table) {
            $table->unsignedInteger('location_id')->primary();
        });
        DB::table('gov_store_ledger_openings')->insert(['location_id' => 7]);
    }

    private function user(bool $super): User
    {
        $user = new class extends User
        {
            public bool $super = false;

            public function isSuperUser()
            {
                return $this->super;
            }
        };
        $user->super = $super;
        $user->id = $super ? 1 : 2;

        return $user;
    }

    /** Runs the guard for a named route; $next stands in for Snipe-IT's controller. */
    private function guard(string $routeName, array $input = [], ?User $user = null, ?\Closure $controller = null, bool $json = false): Response
    {
        $route = Route::getRoutes()->getByName($routeName);
        $this->assertNotNull($route, "Snipe-IT route {$routeName} no longer exists; update govstore-intake");
        $method = in_array('POST', $route->methods(), true) ? 'POST' : 'GET';
        $request = Request::create('/'.$route->uri(), $method, $input, [], [], $json ? ['HTTP_ACCEPT' => 'application/json'] : []);
        $request->setRouteResolver(fn () => $route);
        $request->setUserResolver(fn () => $user);

        return app(EnforceGrnIntake::class)->handle($request, $controller ?? fn () => response('controller ran'));
    }

    public function test_every_guarded_route_exists_upstream(): void
    {
        $names = array_merge(...array_values(config('govstore-intake.routes')));
        $names = array_merge($names, config('govstore-intake.zero_quantity_routes'), [config('govstore-intake.import_route')]);
        foreach ($names as $name) {
            $this->assertTrue(Route::has($name), "Snipe-IT renamed {$name}; the intake guard would silently reopen it");
        }
    }

    public function test_direct_asset_create_is_closed_to_everyone_including_super_users(): void
    {
        foreach (['hardware.create', 'hardware.store', 'clone/hardware'] as $name) {
            $response = $this->guard($name, [], $this->user(true));
            $this->assertTrue($response->isRedirection(), $name);
            $this->assertNotSame('controller ran', $response->getContent());
        }
        $api = $this->guard('api.assets.store', [], $this->user(true), null, true);
        $this->assertSame(403, $api->getStatusCode());
        $this->assertSame('error', json_decode($api->getContent(), true)['status']);
    }

    public function test_licenses_close_only_with_their_switch_and_open_mode_restores_stock_snipe_it(): void
    {
        $this->assertSame('controller ran', $this->guard('licenses.store', [], $this->user(true))->getContent());
        config(['govstore-intake.block_licenses' => true]);
        $this->assertTrue($this->guard('licenses.store', [], $this->user(true))->isRedirection());

        config(['govstore-intake.mode' => 'open']);
        $this->assertSame('controller ran', $this->guard('hardware.store', [], $this->user(true))->getContent());
    }

    public function test_new_stock_records_start_at_quantity_zero(): void
    {
        $this->assertSame('controller ran', $this->guard('consumables.store', ['qty' => 0], $this->user(false))->getContent());
        $this->assertTrue($this->guard('consumables.store', ['qty' => 5], $this->user(false))->isRedirection());
        $this->assertSame(403, $this->guard('api.accessories.store', ['qty' => 2], $this->user(true), null, true)->getStatusCode());
        // Unrelated routes are untouched.
        $this->assertSame('controller ran', $this->guard('consumables.checkout.store', ['qty' => 5], $this->user(false))->getContent());
    }

    public function test_stock_import_is_a_super_user_migration_path_before_ledger_cut_over(): void
    {
        $import = fn (int $office) => function () use ($office) {
            // What Snipe-IT's importer does for each row.
            app(IntakePolicy::class)->checkImportedRecord(new Consumable(['name' => 'Paper A4', 'location_id' => $office]));

            return response()->json(['status' => 'success']);
        };

        $denied = $this->guard('api.imports.importFile', ['import-type' => 'consumable'], $this->user(false), $import(3), true);
        $this->assertSame(500, $denied->getStatusCode());
        $this->assertSame('import-errors', json_decode($denied->getContent(), true)['status']);

        $before = $this->guard('api.imports.importFile', ['import-type' => 'consumable'], $this->user(true), $import(3), true);
        $this->assertSame('success', json_decode($before->getContent(), true)['status'], 'office 3 has no ledger yet');

        $after = $this->guard('api.imports.importFile', ['import-type' => 'consumable'], $this->user(true), function () use ($import) {
            try {
                return $import(7)();
            } catch (ValidationException $e) {
                return response()->json(['message' => $e->getMessage()], 422); // Laravel's handler
            }
        }, true);
        $this->assertSame('import-errors', json_decode($after->getContent(), true)['status'], 'office 7 is on the ledger');
        $this->assertStringContainsString('Paper A4', $after->getContent());

        // User and location imports are not stock.
        $this->assertSame('controller ran', $this->guard('api.imports.importFile', ['import-type' => 'user'], $this->user(false))->getContent());
    }

    public function test_records_saved_outside_an_import_are_not_import_checked(): void
    {
        // GRN posting creates assets directly (CreateAssetsCapability), never through the guarded routes.
        $asset = new Asset;
        $asset->rtd_location_id = 7;
        app(IntakePolicy::class)->checkImportedRecord($asset);
        $this->assertNull(app(IntakePolicy::class)->importViolation());
    }

    public function test_storekeepers_lose_native_asset_create_and_employees_lose_api_keys(): void
    {
        $profiles = config('govstore-permissions.profiles');
        $this->assertNotContains('assets.create', $profiles['inventory_operator']);
        $this->assertContains('consumables.create', $profiles['inventory_operator'], 'stock records are still defined natively, at quantity 0');
        $this->assertNotContains('self.api', $profiles['employee']);
        $this->assertContains('self.api', $profiles['ict_operations']);
    }

    private function offices(int $count): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('locations', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->unsignedInteger('parent_id')->nullable();
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('gov_location_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('location_id');
            $table->timestamps();
        });
        DB::table('companies')->insert(['id' => 1, 'name' => 'Ministry of Land']);
        DB::table('locations')->insert(['id' => 1, 'name' => 'Dhaka Division', 'company_id' => 1]);
        for ($i = 2; $i <= $count + 1; $i++) {
            DB::table('locations')->insert(['id' => $i, 'name' => sprintf('Upazila Land Office %03d', $i), 'parent_id' => 1, 'company_id' => 1]);
            DB::table('gov_location_profiles')->insert(['location_id' => $i]);
        }
        DB::table('locations')->insert(['id' => 999, 'name' => 'Upazila Land Office 100% closed', 'deleted_at' => now()]);
        DB::table('gov_location_profiles')->insert(['location_id' => 999]);
    }

    public function test_super_user_office_search_is_paged_never_the_whole_country(): void
    {
        $this->offices(45);
        $picker = app(OfficePicker::class);
        $super = $this->user(true);

        $this->assertTrue($picker->searches($super));
        $first = $picker->search($super, 'land office');
        $this->assertCount(OfficePicker::PAGE_SIZE, $first['results']);
        $this->assertTrue($first['more']);
        $this->assertSame(['field' => 'location_id', 'value' => 2, 'location_id' => 2, 'text' => 'Upazila Land Office 002',
            'sub' => 'Dhaka Division · Ministry of Land'], $first['results'][0]);
        $this->assertFalse($picker->search($super, 'land office', 3)['more']);
        $this->assertSame(['Upazila Land Office 017'], array_column($picker->search($super, '017')['results'], 'text'));
        // LIKE wildcards are literal; deleted and profile-less offices never appear.
        $this->assertSame([], $picker->search($super, '100%')['results']);
        $this->assertSame([], $picker->search($super, 'Division')['results']);
    }

    public function test_recent_offices_keep_the_last_five_newest_first(): void
    {
        $this->offices(8);
        $picker = app(OfficePicker::class);
        foreach ([2, 3, 4, 5, 6, 7, 3] as $office) {
            $picker->remember(1, $office);
        }
        $this->assertSame([3, 7, 6, 5, 4], $picker->recentIds(1));
        $this->assertSame([3, 7, 6, 5, 4], $picker->recent($this->user(true))->pluck('location_id')->all());
        $this->assertSame([], $picker->recentIds(2), 'recent offices are per user');
    }
}
