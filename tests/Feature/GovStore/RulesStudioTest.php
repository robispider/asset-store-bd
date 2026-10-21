<?php

namespace Tests\Feature\GovStore;

use App\Models\AssetModel;
use App\Models\Category;
use App\Models\Location;
use App\Models\User;
use GovStore\StoreOperations\Http\Controllers\ProfileAdminController;
use GovStore\StoreOperations\Services\ProfileCompilerService;
use GovStore\StoreOperations\Services\RuleTraceService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\AccessDecision;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Mockery;

/**
 * Product rules studio: the layer-by-layer trace must agree with the posting engine, each level
 * reports only what it changes, office policies reach the engine, and every view renders.
 *
 * Country "National Baseline": quantity, warranty 12 months, ledger posting.
 * Finance Division: serial numbers, warranty 24 months. Chattogram office: serial numbers off.
 * Category UPS: quantity (a repeat), asset creation, ledger posting off. Draft 5 is UPS's next version.
 */
class RulesStudioTest extends StoreOperationsWorkflowTest
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach (['companies', 'categories'] as $table) {
            Schema::create($table, function (Blueprint $t) use ($table) {
                $t->increments('id'); $t->string('name'); $t->timestamp('deleted_at')->nullable();
                if ($table === 'categories') $t->string('category_type')->default('asset');
            });
        }
        Schema::create('locations', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('company_id')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('models', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('category_id')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps(); });
        Schema::create('gov_profiles', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('status'); $t->string('version')->nullable(); $t->string('lineage_id')->nullable(); $t->string('scope')->nullable();
            $t->string('owner_type')->nullable(); $t->integer('owner_id')->nullable(); $t->integer('company_id')->nullable(); $t->integer('location_id')->nullable();
            $t->integer('published_by')->nullable(); $t->timestamp('published_at')->nullable(); $t->string('publish_reason')->nullable(); $t->timestamps();
        });
        Schema::create('gov_profile_capabilities', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('profile_id'); $t->string('capability_code'); $t->string('behavior'); $t->json('config_payload')->nullable(); $t->timestamps(); });
        Schema::create('gov_profile_assignments', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('profile_id'); $t->string('target_type'); $t->integer('target_id'); $t->string('scope_level'); $t->integer('scope_id')->nullable();
            $t->integer('assigned_by')->nullable(); $t->timestamp('effective_from'); $t->timestamp('effective_to')->nullable(); $t->timestamps();
        });

        DB::table('companies')->insert(['id' => 20, 'name' => 'Finance Division']);
        DB::table('locations')->insert([['id' => 10, 'name' => 'Dhaka Office', 'company_id' => 20], ['id' => 11, 'name' => 'Chattogram Office', 'company_id' => 20], ['id' => 12, 'name' => 'Lone Office', 'company_id' => null]]);
        DB::table('categories')->insert([['id' => 1, 'name' => 'UPS', 'category_type' => 'asset'], ['id' => 2, 'name' => 'Paper', 'category_type' => 'consumable']]);
        DB::table('models')->insert([['id' => 5, 'name' => 'UPS 1200VA', 'category_id' => 1], ['id' => 6, 'name' => 'UPS 600VA', 'category_id' => 1]]);
        $policies = [
            1 => ['National Baseline', 'PUBLISHED', 'n', ['require_quantity' => ['ENFORCE'], 'require_warranty' => ['ENFORCE', ['warranty_months' => 12]], 'post_inventory' => ['ENFORCE']]],
            2 => ['Finance Division Standard', 'PUBLISHED', 'f', ['require_serial' => ['ENFORCE'], 'require_warranty' => ['ENFORCE', ['warranty_months' => 24]]]],
            3 => ['Chattogram Store Rules', 'PUBLISHED', 'c', ['require_serial' => ['DISABLE']]],
            4 => ['UPS Rules', 'PUBLISHED', 'u', ['require_quantity' => ['ENFORCE'], 'create_assets' => ['ENFORCE'], 'post_inventory' => ['DISABLE']]],
            5 => ['UPS Rules - Copy', 'DRAFT', 'u', ['require_quantity' => ['ENFORCE'], 'post_inventory' => ['DISABLE']]],
        ];
        foreach ($policies as $id => [$name, $status, $lineage, $rules]) {
            DB::table('gov_profiles')->insert(['id' => $id, 'name' => $name, 'status' => $status, 'version' => '1.0', 'lineage_id' => $lineage]);
            foreach ($rules as $code => $rule) {
                DB::table('gov_profile_capabilities')->insert(['profile_id' => $id, 'capability_code' => $code, 'behavior' => $rule[0], 'config_payload' => isset($rule[1]) ? json_encode($rule[1]) : null]);
            }
        }
        foreach ([[1, 'System', 1, 'GLOBAL', null], [2, 'Tenant', 20, 'COMPANY', 20], [3, 'Location', 11, 'LOCATION', 11], [4, 'App\Models\Category', 1, 'NATIVE', null]] as [$profile, $type, $target, $scope, $scopeId]) {
            DB::table('gov_profile_assignments')->insert(['profile_id' => $profile, 'target_type' => $type, 'target_id' => $target, 'scope_level' => $scope, 'scope_id' => $scopeId, 'effective_from' => now()->subDay()]);
        }
        ProfileCompilerService::clearResolvedCache();
        app()->setLocale('en-US');
    }

    public function test_trace_agrees_with_the_engine_and_names_who_decided_each_rule(): void
    {
        $trace = app(RuleTraceService::class)->trace(null, Location::find(10), null, AssetModel::find(5));

        $context = clone app(TenantContext::class);
        $context->locationId = 10;
        $context->companyId = 20;
        $engine = (new ProfileCompilerService($context))->compileItem('App\Models\AssetModel', 5);
        foreach ($trace['rules'] as $code => $rule) {
            $expected = isset($engine[$code]) ? ($engine[$code]['enforced'] ? 'on' : 'off') : 'none';
            $this->assertSame($expected, $rule['result'], $code);
        }

        $this->assertSame(['DEFAULT', 'GLOBAL', 'COMPANY', 'LOCATION', 'CATEGORY', 'MODEL'], array_column($trace['layers'], 'key'));
        $this->assertSame('UPS', $trace['category']->name, 'A model brings its category.');
        $this->assertSame('Finance Division', $trace['company']->name, 'An office brings its ministry.');

        $warranty = $trace['rules']['require_warranty'];
        $this->assertSame('COMPANY', $warranty['decider']);
        $this->assertSame(['GLOBAL'], $warranty['replaced']);
        $this->assertStringContainsString('24', $warranty['value']);
        $this->assertSame('pass', $warranty['cells']['CATEGORY']['state']);
        $this->assertSame('on', $warranty['cells']['CATEGORY']['carry']);

        $posting = $trace['rules']['post_inventory'];
        $this->assertSame(['off', 'CATEGORY', ['GLOBAL']], [$posting['result'], $posting['decider'], $posting['replaced']]);
        $this->assertSame('empty', $trace['rules']['create_assets']['cells']['GLOBAL']['state'], 'Nothing flows before a level first sets a rule.');
    }

    public function test_office_policy_turns_a_ministry_rule_off_only_for_that_office(): void
    {
        $service = app(RuleTraceService::class);
        $this->assertSame('on', $service->trace(null, Location::find(10), Category::find(1), null)['rules']['require_serial']['result']);
        $chattogram = $service->trace(null, Location::find(11), Category::find(1), null)['rules']['require_serial'];
        $this->assertSame(['off', 'LOCATION', ['COMPANY']], [$chattogram['result'], $chattogram['decider'], $chattogram['replaced']]);
    }

    public function test_each_level_lists_only_what_it_changes_from_its_parent(): void
    {
        $service = app(RuleTraceService::class);
        $org = $service->orgTree();
        $ministry = collect($org['companies'])->firstWhere('id', 20);
        $this->assertSame(['add:require_serial', 'change:require_warranty'], array_map(fn ($d) => $d['kind'].':'.$d['code'], $ministry['diffs']));
        $this->assertStringContainsString('12', collect($ministry['diffs'])->firstWhere('code', 'require_warranty')['from']);
        $this->assertSame(['off:require_serial'], array_map(fn ($d) => $d['kind'].':'.$d['code'], collect($ministry['children'])->firstWhere('id', 11)['diffs']));
        $this->assertSame([], collect($ministry['children'])->firstWhere('id', 10)['diffs']);
        $this->assertSame([12], array_column($org['unassigned_offices'], 'id'), 'Offices without a ministry still appear.');

        $layers = $service->trace(null, Location::find(10), Category::find(1), null)['layers'];
        $ups = collect($service->productTree($layers)['asset'])->firstWhere('id', 1);
        $this->assertSame(['off:post_inventory', 'add:create_assets'], array_map(fn ($d) => $d['kind'].':'.$d['code'], $ups['diffs']));
        $this->assertSame(1, $ups['repeats'], 'Requiring the quantity again is a repeat, not a change.');
        $this->assertSame(2, $ups['model_count']);
    }

    public function test_assigning_a_policy_to_an_office_reaches_the_engine(): void
    {
        $request = Request::create('/assign', 'POST', ['target_type' => 'App\Models\Location', 'target_id' => '10', 'profile_id' => 3]);
        app(ProfileAdminController::class)->assignPolicy($request);

        $this->assertSame('Location', DB::table('gov_profile_assignments')->where('profile_id', 3)->where('target_id', 10)->value('target_type'));
        $context = clone app(TenantContext::class);
        $context->locationId = 10;
        $context->companyId = 20;
        $this->assertFalse((new ProfileCompilerService($context))->compileContext()['require_serial']['enforced']);

        // Replacing the office's policy expires the previous one, whichever key it was stored under.
        app(ProfileAdminController::class)->assignPolicy(Request::create('/assign', 'POST', ['target_type' => 'App\Models\Location', 'target_id' => '10', 'profile_id' => 1]));
        $this->assertSame(1, DB::table('gov_profile_assignments')->where('target_id', 10)->where('target_type', 'Location')->whereNull('effective_to')->count());
    }

    public function test_studio_views_render_the_trace_three_ways_and_the_library(): void
    {
        $this->viewing();
        $html = fn (string $view, array $extra = []) => $this->render(['view' => $view, 'location' => 10, 'category' => 1] + $extra);

        $tree = $html('tree');
        $this->assertStringContainsString('Rules that apply when UPS is received at Dhaka Office', $tree);
        $this->assertStringContainsString('Finance Division Standard', $tree);
        $this->assertStringContainsString('data-rst-why="require_warranty"', $tree);
        $this->assertStringContainsString('Replaces National Baseline', $tree);

        $flow = $html('flow');
        $this->assertStringContainsString('is turned off against a higher level', $flow);
        $this->assertStringContainsString('rst-lane__cell--carry-on', $flow);
        $changed = $html('flow', ['changed' => 1]);
        $this->assertStringContainsString('Warranty period required', $changed);
        $this->assertStringNotContainsString('Programme tracking</span>', $changed);

        $family = $html('family');
        $this->assertStringContainsString('Serial numbers required turned off', $family);
        $this->assertStringContainsString('Offices without a ministry', $family);

        $library = $html('policies');
        $this->assertStringContainsString('Drafts · 1', $library);
        $this->assertStringContainsString('UPS Rules - Copy', $library);

        foreach ([$tree, $flow, $family, $library] as $page) {
            $this->assertStringNotContainsString('<x-gs', $page);
            $this->assertStringNotContainsString('storeops::studio.', $page);
        }
    }

    public function test_editor_shows_what_comes_from_above_and_what_the_storekeeper_gets(): void
    {
        $this->viewing();
        $request = Request::create('/edit', 'GET', ['office' => 11]);
        app()->instance('request', $request);
        $html = app(ProfileAdminController::class)->editPolicy($request, 5, app(RuleTraceService::class))->render();

        $this->assertStringContainsString('Coming from above', $html);
        $this->assertStringContainsString('Attached to Category · UPS', $html, 'A draft shows where its published version applies.');
        $this->assertStringContainsString('Overrides National Baseline', $html);
        $this->assertStringContainsString('Same as above — matters only if that level changes', $html);
        $this->assertStringContainsString('Follows Chattogram Store Rules', $html, 'The previewed office decides what arrives from above.');
        $this->assertStringContainsString('Rules that differ from what arrives from above: 1', $html);
        $this->assertStringContainsString('name="rules[post_inventory][behavior]" value="DISABLE"  checked', preg_replace('/\s+checked/', '  checked', $html));
        $this->assertStringNotContainsString('<x-gs', $html);

        $published = app(ProfileAdminController::class)->editPolicy(Request::create('/edit'), 4, app(RuleTraceService::class))->render();
        $this->assertStringContainsString('Published policies are kept as issued', $published);
        $this->assertStringNotContainsString('id="btn_trigger_publish"', $published);
    }

    private function viewing(): void
    {
        $dir = sys_get_temp_dir().'/storeops-studio-layout';
        @mkdir($dir.'/layouts', 0777, true);
        file_put_contents($dir.'/layouts/default.blade.php', "@yield('content')");
        app('view')->getFinder()->prependLocation($dir);
        app('view')->getDispatcher()->forget('composing: layouts.default');
        view()->share('errors', new ViewErrorBag);
        auth()->setUser((new User)->forceFill(['id' => 1]));
        app()->instance(GovAccess::class, Mockery::mock(GovAccess::class)->shouldReceive('decide')
            ->andReturnUsing(fn ($user, $ability) => new AccessDecision(true, $ability, 'test'))->getMock());
    }

    private function render(array $query): string
    {
        $request = Request::create('/rules', 'GET', $query);
        app()->instance('request', $request);

        return app(ProfileAdminController::class)->index($request, app(RuleTraceService::class))->render();
    }
}
