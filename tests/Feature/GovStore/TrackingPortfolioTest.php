<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use Carbon\CarbonImmutable;
use GovStore\Tracking\Http\Controllers\InitiativeController;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingDelivery;
use GovStore\Tracking\Services\TrackingMonitorService;
use GovStore\Tracking\Services\TrackingPortfolioService;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Mockery;

/** Uses only the inherited in-memory SQLite fixture, never the live database. */
class TrackingPortfolioTest extends TrackingWorkflowTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->viewer();
        $directory = storage_path('framework/testing/tracking-portfolio/layouts');
        if (!is_dir($directory)) mkdir($directory, 0777, true);
        file_put_contents($directory.'/default.blade.php', '@yield("content")');
        app('view')->getFinder()->prependLocation(dirname($directory));
        app('view')->getDispatcher()->forget('composing: layouts.default');
    }

    protected function tearDown(): void
    {
        $file = storage_path('framework/testing/tracking-portfolio/layouts/default.blade.php');
        if (is_file($file)) unlink($file);
        parent::tearDown();
    }

    private function viewer(bool $nativeAdmin = false, int $id = 1): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->location_id = 10;
        $user->shouldReceive('isSuperUser')->andReturn(false);
        $user->shouldReceive('hasAccess')->andReturn($nativeAdmin);
        auth()->setUser($user);
    }

    private function programme(string $title = 'Visible programme', string $status = 'Active', ?string $role = 'MONITOR', int $company = 20): array
    {
        $initiative = Initiative::withoutGlobalScopes()->create(['title' => $title, 'owner_company_id' => $company, 'primary_funding' => 'ADP', 'status' => $status]);
        if ($role) DB::table('gov_tracking_operation_units')->insert(['initiative_id' => $initiative->id, 'user_id' => 1, 'designation' => $role]);
        $fund = DB::table('gov_funding_types')->insertGetId(['name' => 'Fund', 'primary_type' => 'ADP']);
        $code = TrackingCode::create(['initiative_id' => $initiative->id, 'funding_type_id' => $fund, 'tracking_code' => 'PORT-'.$initiative->id,
            'task_title' => $title, 'specificity_level' => '3_MATRIX', 'fiscal_year' => '2026-2027', 'status' => 'ACTIVE', 'activated_at' => '2026-07-01']);
        $target = DB::table('gov_tracking_targets')->insertGetId(['tracking_code_id' => $code->id, 'category_id' => 1, 'planned_qty' => 10, 'economic_code' => 'TEST']);
        DB::table('gov_tracking_allocations')->insert([['target_id' => $target, 'location_id' => 10, 'allocated_qty' => 5], ['target_id' => $target, 'location_id' => 11, 'allocated_qty' => 5]]);

        return [$initiative, $code];
    }

    private function receipt(TrackingCode $code, int $location = 10, int $quantity = 10, ?float $value = 100, string $grn = 'SHARED'): void
    {
        $id = DB::table('gov_tracking_deliveries')->insertGetId(['tracking_code_id' => $code->id, 'delivery_key' => hash('sha256', uniqid('', true)), 'created_at' => '2026-10-15',
            'receipt_snapshot' => json_encode(['quantity' => $quantity, 'total_cost' => $value, 'category_id' => 1, 'location_id' => $location,
                'grn' => $grn, 'occurred_at' => '2026-10-15', 'supplier_id' => 1])]);
        DB::table('gov_tracking_associations')->insert(['tracking_code_id' => $code->id, 'delivery_id' => $id, 'category_id' => 1,
            'location_id' => $location, 'quantity' => $quantity, 'status' => 'ACTIVE', 'associatable_type' => TrackingDelivery::class,
            'associatable_id' => $id, 'created_at' => '2026-10-15', 'updated_at' => '2026-10-15']);
    }

    private function portfolio(array $filters = []): array
    {
        return app(TrackingPortfolioService::class)->portfolio(array_replace(['view' => 'table', 'status' => 'all', 'funding' => 'all', 'sort' => 'priority', 'direction' => 'asc'], $filters), CarbonImmutable::parse('2026-10-19'));
    }

    public function test_summary_matches_monitor_math_and_excludes_draft_receipts(): void
    {
        [$initiative, $code] = $this->programme();
        $this->receipt($code);
        $draft = $code->replicate(); $draft->tracking_code = 'DRAFT'; $draft->status = 'DRAFT'; $draft->save();
        $this->receipt($draft, 10, 999, 9999);
        foreach (['3_MATRIX', '2_CATEGORY', '1_BLANKET'] as $plan) {
            $code->update(['specificity_level' => $plan]);
            $monitor = app(TrackingMonitorService::class)->programme($initiative, [], CarbonImmutable::parse('2026-10-19'));
            $p = $this->portfolio()['programmes'][0];
            foreach (['delivered', 'expected', 'value', 'known_value', 'value_complete', 'grn_count', 'supplier_count', 'active_count', 'draft_count'] as $key) $this->assertEquals($monitor[$key], $p[$key], $plan.' '.$key);
            $this->assertSame(collect($monitor['receipts'])->pluck('date')->max(), $p['last']);
            $this->assertSame($monitor['reached'], count($p['reached_ids']));
            $this->assertSame($monitor['allocated_offices'], count($p['allocated_ids']));
            $this->assertEquals(100, $p['value']);
        }
    }

    public function test_visibility_revocation_native_admin_and_company_oversight_are_distinct(): void
    {
        [$own, $code] = $this->programme(); $this->receipt($code);
        [, $foreign] = $this->programme('Secret foreign programme', 'Active', null, 21); $this->receipt($foreign, 10, 99, 9999);
        $data = $this->portfolio();
        $this->assertSame(1, $data['visible_count']); $this->assertEquals(100, $data['summary']['value']);
        $this->assertFalse($data['programmes'][0]['can_edit']);
        $this->assertSame(0, $this->portfolio(['q' => 'Secret'])['base_count']);
        DB::table('gov_tracking_operation_units')->where('initiative_id', $own->id)->delete();
        $this->viewer(true);
        $this->assertSame(0, $this->portfolio()['visible_count']);
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        $this->assertSame(0, $this->portfolio()['visible_count']);
        DB::table('gov_company_admins')->insert(['user_id' => 1, 'company_id' => 20]);
        $admin = $this->portfolio();
        $this->assertSame(1, $admin['visible_count']); $this->assertTrue($admin['programmes'][0]['can_edit']);
        $this->assertFalse($admin['programmes'][0]['mine']);
        $this->assertSame(0, $this->portfolio(['mine' => 1])['base_count']);
        auth()->forgetUser();
        $this->assertSame(0, $this->portfolio()['visible_count']);
    }

    public function test_distinct_receipts_suppliers_and_offices_are_not_summed_across_programmes(): void
    {
        [, $a] = $this->programme('A'); [, $b] = $this->programme('B');
        $this->receipt($a, 10, 3, 30); $this->receipt($b, 10, 2, 20); $this->receipt($b, 11, 1, 10);
        $summary = $this->portfolio()['summary'];
        $this->assertEquals(60, $summary['value']);
        $this->assertSame(2, $summary['grn_count']); // Same number in two offices is two receipts.
        $this->assertSame(1, $summary['supplier_count']);
        $this->assertSame(2, $summary['reached']); $this->assertSame(2, $summary['allocated']); $this->assertSame(0, $summary['waiting']);
    }

    public function test_incomplete_values_and_unknown_progress_are_not_presented_as_zero_or_healthy(): void
    {
        [, $a] = $this->programme('Known'); [, $b] = $this->programme('Unknown');
        $this->receipt($a, 10, 1, 25); $this->receipt($b, 10, 1, null);
        $summary = $this->portfolio()['summary'];
        $this->assertNull($summary['value']); $this->assertFalse($summary['value_complete']); $this->assertEquals(25, $summary['known_value']);
        [$empty, $code] = $this->programme('No active codes'); $code->delete();
        $p = collect($this->portfolio()['programmes']->items())->firstWhere('id', $empty->id);
        $this->assertSame('no_target', $p['health']); $this->assertNull($p['delivered']);
    }

    public function test_filters_tabs_and_closed_programmes_do_not_change_operational_scope(): void
    {
        foreach (['Active', 'Planning', 'Closed', 'Archived'] as $status) { [, $code] = $this->programme($status, $status); $this->receipt($code); }
        $planning = $this->portfolio(['status' => 'Planning']);
        $this->assertSame(1, $planning['programmes']->total()); $this->assertSame(4, $planning['base_count']);
        $this->assertSame(1, $planning['summary']['operational']); $this->assertEquals(100, $planning['summary']['value']);
        $this->assertSame(4, $this->portfolio(['q' => 'Tracking tester'])['base_count']);
        $this->assertSame(0, $this->portfolio(['funding' => 'REVENUE'])['base_count']);
    }

    public function test_table_and_cards_render_identical_ids_in_both_languages_and_escape_names(): void
    {
        foreach (['Active', 'Planning', 'Closed', 'Archived'] as $status) $this->programme('<script>bad()</script> '.$status, $status);
        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale); $ids = [];
            foreach (['table', 'cards'] as $view) {
                $html = view('govtracking::initiatives.index', $this->portfolio(['view' => $view]))->render();
                $this->assertStringNotContainsString('<script>bad()</script>', $html); $this->assertStringContainsString('&lt;script&gt;bad()&lt;/script&gt;', $html);
                $this->assertStringNotContainsString('<x-gs::', $html); $this->assertStringNotContainsString('govtracking::portfolio.', $html);
                $this->assertStringNotContainsString('govtracking::general.', $html);
                preg_match_all('/data-portfolio-programme="(\d+)"/', $html, $matches); $ids[$view] = $matches[1];
                $this->assertCount(4, $ids[$view]);
                $this->assertStringNotContainsString('Properties</a>', $html);
                if ($view === 'table') {
                    $dom = new \DOMDocument; @$dom->loadHTML($html); $xpath = new \DOMXPath($dom);
                    $this->assertSame(4, $xpath->query('//tr[@data-monitor-detail and not(@hidden)]')->length);
                    $this->assertSame(4, $xpath->query('//button[@data-monitor-toggle and @hidden]')->length);
                }
            }
            $this->assertSame($ids['table'], $ids['cards']);
        }
        $en = require base_path('packages/gov-store/tracking/src/resources/lang/en-US/portfolio.php');
        $bn = require base_path('packages/gov-store/tracking/src/resources/lang/bn-BD/portfolio.php');
        foreach ([null, 'health', 'status', 'sorts'] as $key) $this->assertSame(array_keys($key ? $en[$key] : $en), array_keys($key ? $bn[$key] : $bn));
    }

    public function test_server_sorting_applies_before_pagination_and_keeps_view_query(): void
    {
        for ($i = 1; $i <= 22; $i++) $this->programme(sprintf('Programme %02d', $i), 'Planning');
        $first = $this->portfolio(['sort' => 'title', 'direction' => 'desc', 'view' => 'cards']);
        $second = $this->portfolio(['sort' => 'title', 'direction' => 'desc', 'view' => 'cards', 'page' => 2]);
        $this->assertSame('Programme 22', $first['programmes'][0]['title']); $this->assertSame('Programme 02', $second['programmes'][0]['title']);
        $this->assertSame(22, $first['programmes']->total()); $this->assertCount(2, $second['programmes']);
        $this->assertStringContainsString('view=cards', $first['programmes']->nextPageUrl());
        $last = $this->portfolio(['page'=>999]);
        $this->assertSame(2, $last['programmes']->currentPage()); $this->assertCount(2, $last['programmes']);
    }

    public function test_view_preference_is_per_actor_and_invalid_filters_are_rejected(): void
    {
        $session = new Store('portfolio-test', new ArraySessionHandler(120)); $session->start();
        $index = function (array $query) use ($session) {
            $request = Request::create('/gov-store/admin/tracking/initiatives', 'GET', $query); $request->setLaravelSession($session);
            return app(InitiativeController::class)->index($request, app(TrackingPortfolioService::class))->getData();
        };
        $this->assertSame('cards', $index(['view' => 'cards'])['filters']['view']); $this->assertSame('cards', $index([])['filters']['view']);
        $this->viewer(false, 2); $this->assertSame('table', $index([])['filters']['view']);
        foreach (['view' => '../error', 'sort' => 'owner_company_id', 'mine' => '2', 'status' => 'Deleted', 'page' => '-1'] as $key => $value) {
            try { $index([$key => $value]); $this->fail('Invalid filter accepted'); }
            catch (ValidationException $e) { $this->assertArrayHasKey($key, $e->errors()); }
        }
    }

    public function test_query_growth_is_batched_for_one_ten_and_fifty_programmes(): void
    {
        $measurements = []; $previous = 0;
        foreach ([1, 10, 50] as $size) {
            for ($i = $previous; $i < $size; $i++) { [, $code] = $this->programme('Performance '.$i); $this->receipt($code); }
            DB::flushQueryLog(); DB::enableQueryLog(); $started = hrtime(true);
            $data = $this->portfolio();
            $measurements[$size] = ['queries' => count(DB::getQueryLog()), 'ms' => round((hrtime(true)-$started)/1e6, 1), 'peak_mb' => round(memory_get_peak_usage(true)/1048576, 1)];
            DB::disableQueryLog(); $this->assertSame($size, $data['visible_count']); $previous = $size;
        }
        $this->assertSame($measurements[1]['queries'], $measurements[50]['queries']);
        $this->assertLessThan(2000, $measurements[50]['ms']);
        fwrite(STDERR, PHP_EOL.'Portfolio isolated benchmark: '.json_encode($measurements).PHP_EOL);
    }

    public function test_readiness_requires_head_and_officer_and_priority_uses_actual_issue_severity(): void
    {
        [$planning] = $this->programme('Setup', 'Planning', 'HEAD');
        $setup = collect($this->portfolio()['programmes']->items())->firstWhere('id', $planning->id);
        $this->assertFalse($setup['readiness'][0]['done']); $this->assertTrue($setup['can_edit']);
        DB::table('users')->insert(['id' => 2, 'first_name' => 'Officer']);
        DB::table('gov_tracking_operation_units')->insert(['initiative_id' => $planning->id, 'user_id' => 2, 'designation' => 'OFFICER']);
        $setup = collect($this->portfolio()['programmes']->items())->firstWhere('id', $planning->id);
        $this->assertTrue($setup['readiness'][0]['done']);
        [$serious] = $this->programme('Stalled'); // No movement since July: serious.
        [$critical, $code] = $this->programme('Incomplete'); $this->receipt($code, 10, 1, null);
        $data = $this->portfolio();
        $this->assertSame($critical->id, $data['priority']['id']);
        $this->assertSame($critical->id, $data['programmes'][0]['id']);
        $this->assertSame('critical', $data['programmes'][0]['attention'][0]['severity']);
    }

    public function test_numeric_sorting_uses_real_values_and_places_unknown_values_last_in_both_directions(): void
    {
        [$small, $smallCode] = $this->programme('Small'); $this->receipt($smallCode, 10, 1, 2);
        [$large, $largeCode] = $this->programme('Large'); $this->receipt($largeCode, 10, 1, 10);
        [$unknown, $unknownCode] = $this->programme('Unknown'); $this->receipt($unknownCode, 10, 1, null);
        $this->assertSame([$small->id,$large->id,$unknown->id], array_column($this->portfolio(['sort'=>'value','direction'=>'asc'])['programmes']->items(),'id'));
        $this->assertSame([$large->id,$small->id,$unknown->id], array_column($this->portfolio(['sort'=>'value','direction'=>'desc'])['programmes']->items(),'id'));
    }
}
