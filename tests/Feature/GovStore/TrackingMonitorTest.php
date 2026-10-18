<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use Carbon\CarbonImmutable;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Http\Middleware\RequireGovAbility;
use GovStore\TenantScope\Services\GovAccess;
use GovStore\TenantScope\Services\NationalChangeReview;
use GovStore\Tracking\Http\Controllers\TrackingCodeController;
use GovStore\Tracking\Http\Controllers\TrackingOfficeController;
use GovStore\Tracking\Http\Middleware\SafeTrackingResponse;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingDelivery;
use GovStore\Tracking\Services\TrackingAuthorizationService;
use GovStore\Tracking\Services\TrackingMonitorCharts;
use GovStore\Tracking\Services\TrackingMonitorService;
use GovStore\Tracking\Services\TrackingOfficeAccess;
use Illuminate\Http\Request;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Every inherited workflow test also runs against the extended tracking schema. */
class TrackingMonitorTest extends TrackingWorkflowTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $context = app(TenantContext::class);
        $context->locationId = 10;
        $context->companyId = 20;
        $context->allowedLocationIds = [10];
        config(['tracking.office_directory_enabled' => true]);
        $layoutDir = storage_path('framework/testing/tracking-monitor/layouts');
        if (! is_dir($layoutDir)) {
            mkdir($layoutDir, 0777, true);
        }
        file_put_contents($layoutDir.'/default.blade.php', '@yield("content")');
        app('view')->getFinder()->prependLocation(dirname($layoutDir));
        app('view')->getDispatcher()->forget('composing: layouts.default');

    }

    protected function tearDown(): void
    {
        $file = storage_path('framework/testing/tracking-monitor/layouts/default.blade.php');
        if (is_file($file)) {
            unlink($file);
        }
        parent::tearDown();
    }

    private function viewer(string $role = 'MONITOR'): void
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->location_id = 10;
        $user->shouldReceive('isSuperUser')->andReturn($role === 'SUPER');
        $user->shouldReceive('hasAccess')->andReturn($role === 'NATIVE_ADMIN');
        auth()->setUser($user);
    }

    private function programmeFixture(string $title = 'Visible programme', int $company = 20, ?string $designation = 'MONITOR'): array
    {
        $i = Initiative::withoutGlobalScopes()->create(['title' => $title, 'owner_company_id' => $company, 'primary_funding' => 'ADP', 'status' => 'Active']);
        $fund = DB::table('gov_funding_types')->insertGetId(['name' => 'Fund', 'primary_type' => 'ADP']);
        $c = TrackingCode::create(['initiative_id' => $i->id, 'funding_type_id' => $fund, 'tracking_code' => 'CODE-'.$i->id,
            'task_title' => $title, 'specificity_level' => '3_MATRIX', 'fiscal_year' => '2026-2027', 'status' => 'ACTIVE', 'activated_at' => '2026-07-01']);
        $target = DB::table('gov_tracking_targets')->insertGetId(['tracking_code_id' => $c->id, 'category_id' => 1, 'planned_qty' => 10]);
        DB::table('gov_tracking_allocations')->insert([['target_id' => $target, 'location_id' => 10, 'allocated_qty' => 5], ['target_id' => $target, 'location_id' => 11, 'allocated_qty' => 5]]);
        if ($designation) {
            DB::table('gov_tracking_operation_units')->insert(['initiative_id' => $i->id, 'user_id' => 1, 'designation' => $designation]);
        }

        return [$i, $c, $target];
    }

    private function receipt(TrackingCode $code, int $location = 10, int $quantity = 10, ?float $value = 100): void
    {
        $delivery = DB::table('gov_tracking_deliveries')->insertGetId(['tracking_code_id' => $code->id, 'delivery_key' => hash('sha256', uniqid('', true)),
            'created_at' => '2026-10-15', 'receipt_snapshot' => json_encode(['quantity' => $quantity, 'total_cost' => $value, 'category_id' => 1, 'location_id' => $location,
                'grn' => 'RECEIPT-'.$code->id, 'occurred_at' => '2026-10-15', 'supplier_id' => null, 'manufacturer_id' => null, 'model_id' => null])]);
        DB::table('gov_tracking_associations')->insert(['tracking_code_id' => $code->id, 'delivery_id' => $delivery, 'category_id' => 1, 'location_id' => $location,
            'quantity' => $quantity, 'status' => 'ACTIVE', 'associatable_type' => TrackingDelivery::class, 'associatable_id' => $delivery,
            'created_at' => '2026-10-15', 'updated_at' => '2026-10-15']);
    }

    public function test_matrix_read_model_excludes_drafts_and_legacy_facts_and_keeps_category_names(): void
    {
        $this->viewer();
        [$i,$code] = $this->programmeFixture();
        $this->receipt($code);
        [$hidden,$other] = $this->programmeFixture('Hidden programme', 21, null);
        $this->receipt($other, 10, 99, 999);
        $draft = $code->replicate();
        $draft->tracking_code = 'DRAFT-CODE';
        $draft->status = 'DRAFT';
        $draft->save();
        DB::table('gov_tracking_targets')->insert(['tracking_code_id' => $draft->id, 'category_id' => 1, 'planned_qty' => 1000]);
        DB::table('gov_tracking_fact_deliveries')->insert(['initiative_id' => $i->id, 'tracking_code_id' => $code->id, 'funding_type_id' => $code->funding_type_id,
            'fiscal_year' => $code->fiscal_year, 'location_id' => 10, 'category_id' => 1, 'received_qty' => 10000, 'total_cost' => 99999, 'transaction_count' => 5]);
        $m = app(TrackingMonitorService::class)->programme($i, [], CarbonImmutable::parse('2026-10-18'));
        $this->assertEquals(.5, $m['delivered']);
        $this->assertEquals(100, $m['value']);
        $this->assertEquals(10, $m['received']);
        $this->assertSame(1, $m['draft_count']);
        $this->assertSame('Equipment', $m['items'][0]['name']);
        $this->assertEquals(1, $m['waiting_offices']);
        $this->assertSame('over_allocation', $m['codes'][0]['health']);
        $this->assertNotContains($hidden->id, app(TrackingOfficeAccess::class)->programmes()->pluck('id')->all());
    }

    public function test_office_only_access_never_reveals_a_hidden_programme_or_changes_totals(): void
    {
        $this->viewer('STOREKEEPER');
        [$hidden,$code] = $this->programmeFixture('Secret ministry', 21, null);
        $this->receipt($code, 10, 5, 500);
        $controller = app(TrackingOfficeController::class);
        $view = $controller->all(10);
        $data = $view->getData();
        $this->assertSame([], $data['programmes']);
        $this->assertSame([], $data['slices']);
        $this->assertEquals(0, $data['summary']['received']);
        $this->assertTrue($data['canInventory']);
        $this->assertCount(0, $data['assets']);
        $directory = $controller->index()->getData();
        $this->assertCount(1, $directory['offices']);
        $this->assertSame(0, $directory['offices'][0]['programme_count']);
        $this->expectException(HttpExceptionInterface::class);
        $controller->programme($hidden, 10);
    }

    public function test_programme_key_fallback_and_inventory_key_are_independent(): void
    {
        $this->viewer();
        [$i,$c] = $this->programmeFixture();
        $access = app(TrackingOfficeAccess::class);
        $access->office($i, 11, $c);
        $this->assertFalse($access->officeKey(11));
        $this->assertFalse($access->inventoryKey(11, 21));
        $fallback = app(TrackingOfficeController::class)->all(11);
        $this->assertSame(route('gov.tracking.initiatives.offices.show', [$i->id, 11]), $fallback->getTargetUrl());
        DB::table('gov_tracking_operation_units')->delete();
        try {
            $access->office($i, 11, $c);
            $this->fail('Revoked programme membership must deny immediately.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_new_routes_have_exactly_one_registered_ability_and_monitor_cannot_mutate(): void
    {
        $this->viewer();
        [$i] = $this->programmeFixture();
        foreach (Route::getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'gov-store/admin/tracking') || str_starts_with($route->uri(), 'gov-store/api/tracking')) {
                $abilities = array_values(array_filter($route->gatherMiddleware(), fn ($m) => str_starts_with($m, 'gov.can:')));
                $this->assertCount(1, $abilities, $route->uri());
                $this->assertArrayHasKey(substr($abilities[0], 8), config('govstore-abilities'));
            }
        }
        $access = app(GovAccess::class);
        $user = auth()->user();
        $this->assertTrue($access->decide($user, 'tracking.programmes.view')->allowed);
        $this->assertFalse($access->decide($user, 'tracking.codes.manage')->allowed);
        $this->assertFalse($access->decide($user, 'tracking.codes.activate')->allowed);
        $this->assertFalse($access->decide($user, 'tracking.documents.manage')->allowed);
        $this->assertFalse(app(TrackingAuthorizationService::class)->allows($i, ['HEAD', 'OFFICER']));
    }

    public function test_foreign_nested_ids_and_disconnected_offices_and_disabled_directory_fail(): void
    {
        $this->viewer();
        [$a,$code] = $this->programmeFixture();
        [$b] = $this->programmeFixture('Other');
        try {
            app(TrackingCodeController::class)->show($b, $code);
            $this->fail('Foreign nested ID');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        try {
            app(TrackingOfficeController::class)->programme($a, 9999);
            $this->fail('Unconnected office');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
        config(['tracking.office_directory_enabled' => false]);
        try {
            app(TrackingOfficeController::class)->index();
            $this->fail('Disabled directory');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(404, $e->getStatusCode());
        }
    }

    public function test_company_admin_sees_own_programmes_but_not_another_ministrys_office_deliveries(): void
    {
        $this->viewer('ADMIN');
        [$own,$ownCode] = $this->programmeFixture('Owned programme', 20, null);
        [$hidden,$hiddenCode] = $this->programmeFixture('Hidden ministry programme', 21, null);
        DB::table('gov_company_admins')->insert(['user_id' => 1, 'company_id' => 20]);
        $this->receipt($ownCode, 10, 2, 20);
        $this->receipt($hiddenCode, 10, 100, 9999);
        app(TenantContext::class)->allowedLocationIds = [10, 11];
        $data = app(TrackingOfficeController::class)->all(10)->getData();
        $this->assertCount(1, $data['programmes']);
        $this->assertSame($own->id, $data['programmes'][0]['initiative']->id);
        $this->assertEquals(2, $data['summary']['received']);
        $this->assertEquals(20, $data['summary']['value']);
        $this->assertTrue($data['canInventory']);
    }

    public function test_jurisdiction_and_storekeeper_roles_alone_do_not_grant_programme_access(): void
    {
        $this->viewer('ICT');
        [$i,$c] = $this->programmeFixture('Restricted', 20, null);
        $this->receipt($c, 11, 5, 100);
        DB::table('gov_ict_jurisdictions')->insert(['user_id' => 1]);
        $context = app(TenantContext::class);
        $context->allowedLocationIds = [10, 11];
        $data = app(TrackingOfficeController::class)->all(11)->getData();
        $this->assertSame([], $data['programmes']);
        $this->assertFalse($data['canInventory']);
        $this->assertCount(2, app(TrackingOfficeController::class)->index()->getData()['offices']);
        DB::table('gov_tracking_operation_units')->insert(['initiative_id' => $i->id, 'user_id' => 1, 'designation' => 'MONITOR']);
        $data = app(TrackingOfficeController::class)->all(11)->getData();
        $this->assertCount(1, $data['programmes']);
        $this->assertEquals(5, $data['summary']['received']);
        $this->assertFalse($data['canInventory']);
    }

    public function test_native_admin_is_not_superuser_and_denied_office_links_stay_in_scope(): void
    {
        $this->viewer('NATIVE_ADMIN');
        [$i] = $this->programmeFixture('Restricted', 20, null);
        $this->assertFalse(app(GovAccess::class)->decide(auth()->user(), 'tracking.programmes.view')->allowed);
        $response = app(TrackingOfficeController::class)->all(11);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame([10], $response->original->getData()['ownOffices']->pluck('id')->all());
        $this->viewer('SUPER');
        $data = app(TrackingOfficeController::class)->all(11)->getData();
        $this->assertCount(1, $data['programmes']);
        $this->assertTrue($data['canInventory']);
    }

    public function test_filters_change_all_metrics_and_drafts_never_enter_live_totals(): void
    {
        $this->viewer();
        [$i,$c] = $this->programmeFixture();
        $this->receipt($c, 10, 2, 20);
        $draft = $c->replicate();
        $draft->status = 'DRAFT';
        $draft->tracking_code = 'SEARCH-DRAFT';
        $draft->save();
        DB::table('gov_tracking_targets')->insert(['tracking_code_id' => $draft->id, 'category_id' => 1, 'planned_qty' => 1000]);
        $service = app(TrackingMonitorService::class);
        $m = $service->programme($i, ['status' => 'DRAFT']);
        $this->assertCount(1, $m['codes']);
        $this->assertNull($m['delivered']);
        $this->assertEquals(0, $m['value']);
        $this->assertSame([], $m['receipts']);
        $this->assertSame([], $m['offices']);
        $this->assertEquals(0, $m['reached']);
        $this->assertCount(1, $service->programme($i, ['q' => 'SEARCH-DRAFT'])['codes']);
        $this->assertSame([], $service->programme($i, ['q' => 'unmatched'])['codes']);
        $this->assertSame([], $service->programme($i, ['plan' => '1_BLANKET'])['receipts']);
        $this->assertCount(1, $service->programme($i, ['status' => 'ACTIVE', 'q' => 'Office B'])['codes']);
    }

    public function test_named_ability_wrapper_rolls_back_rendered_mutation_failure_and_records_no_success(): void
    {
        $this->viewer();
        [$i,$c] = $this->programmeFixture('Writable', 20, 'HEAD');
        $request = Request::create('/tracking-test', 'POST');
        $request->setUserResolver(fn () => auth()->user());
        $request->setRouteResolver(fn () => (new \Illuminate\Routing\Route('POST', 'tracking-test', fn () => null))->middleware('gov.can:tracking.codes.manage'));
        $session = new Store('tracking-test', new ArraySessionHandler(120));
        $session->start();
        $request->setLaravelSession($session);
        $safe = app(SafeTrackingResponse::class);
        $ability = app(RequireGovAbility::class);
        $response = $safe->handle($request, fn ($r) => $ability->handle($r, function () use ($c) {
            $c->update(['task_title' => 'Must roll back']);

            return response('failed', 422);
        }, 'tracking.codes.manage'));
        $this->assertSame(422, $response->getStatusCode());
        $this->assertSame('Writable', $c->fresh()->task_title);
        $this->assertSame(0, DB::transactionLevel());
        $this->assertSame(0, DB::table('gov_access_events')->where('outcome', 'allowed')->count());
        $this->assertSame(1, DB::table('gov_access_events')->where('outcome', 'failed')->count());
    }

    public function test_unknown_receipt_is_not_plotted_as_zero_and_repair_does_not_delete_history(): void
    {
        $this->viewer();
        [$i,$c] = $this->programmeFixture();
        DB::table('gov_tracking_associations')->insert(['tracking_code_id' => $c->id, 'category_id' => 1, 'location_id' => 10, 'quantity' => 2, 'status' => 'ACTIVE',
            'associatable_type' => InventoryMovement::class, 'associatable_id' => 0, 'created_at' => '2026-10-15']);
        $m = app(TrackingMonitorService::class)->programme($i);
        $code = $m['codes'][0];
        $this->assertNull($m['value']);
        $this->assertSame(1, $m['unknown_count']);
        $chart = app(TrackingMonitorCharts::class)->target($code, $code['targets'][0], $m['today']);
        $this->assertSame('', trim($chart['path']));
        $this->artisan('govstore:tracking-rebuild-facts', ['--code' => $c->id, '--apply' => true])->assertFailed();
        $this->assertEquals(2, DB::table('gov_tracking_associations')->sum('quantity'));
    }

    public function test_head_can_activate_draft_once_and_approximate_backfill_does_not_create_archived_dates(): void
    {
        $this->viewer();
        [$i,$c] = $this->programmeFixture('Draft', 20, 'HEAD');
        $c->update(['status' => 'DRAFT', 'activated_at' => null]);
        app(TrackingCodeController::class)->activate($i, $c);
        $this->assertSame('ACTIVE', $c->fresh()->status);
        $this->assertNotNull($c->fresh()->activated_at);
        $this->assertFalse($c->fresh()->activation_is_approximate);
        try {
            app(TrackingCodeController::class)->activate($i, $c->fresh());
            $this->fail('Replayed activation');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertSame(1, DB::table('gov_tracking_timeline')->where('event_type', 'CODE_ACTIVATED')->count());
    }

    public function test_monitor_code_office_and_report_blade_render_without_uncompiled_tags(): void
    {
        $this->viewer();
        [$i,$c] = $this->programmeFixture();
        $this->receipt($c);
        // Only the parent layout is stubbed; actual package templates/components are compiled and rendered.
        $monitor = app(TrackingMonitorService::class)->programme($i);
        $views = [view('govtracking::initiatives.monitor', compact('i', 'monitor') + ['initiative' => $i]),
            app(TrackingCodeController::class)->show($i, $c), app(TrackingOfficeController::class)->programme($i, 10),
            view('govtracking::initiatives.report', ['initiative' => $i, 'monitor' => $monitor]), app(TrackingOfficeController::class)->index()];
        foreach ($views as $view) {
            $html = $view->render();
            $this->assertStringNotContainsString('<x-gs::', $html);
            $this->assertStringNotContainsString('govtracking::general.monitor.', $html);
            $this->assertStringContainsString('Visible programme', $html);
        }

    }

    public function test_funding_settings_review_is_bound_to_the_funding_row_and_code_links(): void
    {
        $this->viewer('SUPER');
        [, $code] = $this->programmeFixture();
        $route = Route::getRoutes()->getByName('gov.tracking.funding-types.destroy');
        $route->bind(Request::create('/gov-store/admin/tracking/funding-types/'.$code->funding_type_id, 'DELETE'));
        $request = Request::create('/gov-store/admin/tracking/funding-types/'.$code->funding_type_id, 'DELETE');
        $request->headers->set('Accept', 'application/json');
        $request->setUserResolver(fn () => auth()->user());
        $request->setRouteResolver(fn () => $route);
        $request->setLaravelSession(app('session')->driver('array'));
        $review = app(NationalChangeReview::class);
        $this->assertTrue(config('govstore-abilities')['tracking.settings.manage']['national']);
        $response = $review->handle($request, 'tracking.settings.manage');
        $token = basename($response->getData(true)['review_url']);
        DB::table('gov_funding_types')->where('id', $code->funding_type_id)->update(['name' => 'Changed after review']);
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Review funding removal']);
        try {
            $review->handle($request, 'tracking.settings.manage');
            $this->fail('A stale funding review must fail.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        DB::table('gov_funding_types')->where('id', $code->funding_type_id)->update(['name' => 'Fund']);
        $code->delete();
        $request->replace(['review_token' => $token, 'confirmation' => 'CHANGE', 'change_reason' => 'Review funding removal']);
        try {
            $review->handle($request, 'tracking.settings.manage');
            $this->fail('Changed code links must invalidate the review too.');
        } catch (HttpExceptionInterface $e) {
            $this->assertSame(409, $e->getStatusCode());
        }
        $this->assertSame(0, DB::table('gov_access_review_uses')->count());
    }

    public function test_programme_blade_renders_with_empty_draft_and_undated_receipt_sparklines(): void
    {
        $this->viewer();
        [$initiative, $code] = $this->programmeFixture();
        $draft = $code->replicate();
        $draft->status = 'DRAFT';
        $draft->tracking_code = 'DRAFT-WITHOUT-RECEIPTS';
        $draft->save();
        DB::table('gov_tracking_associations')->insert(['tracking_code_id' => $code->id, 'category_id' => 1, 'location_id' => 10, 'quantity' => 2, 'status' => 'ACTIVE',
            'associatable_type' => InventoryMovement::class, 'associatable_id' => 0]);
        $monitor = app(TrackingMonitorService::class)->programme($initiative);
        $html = view('govtracking::initiatives.monitor', compact('initiative', 'monitor'))->render();
        $this->assertStringContainsString('DRAFT-WITHOUT-RECEIPTS', $html);
        $this->assertStringContainsString(__('govtracking::general.monitor.incomplete_receipts'), $html);
        $this->assertStringNotContainsString('<x-gs::', $html);
    }

    public function test_monitor_localizes_legacy_category_labels_but_searches_both_languages(): void
    {
        $this->viewer();
        [$initiative, $code] = $this->programmeFixture();
        $this->receipt($code);
        $service = app(TrackingMonitorService::class);
        foreach (['Desktop Computers / ডেস্কটপ কম্পিউটার' => ['Desktop Computers', 'ডেস্কটপ কম্পিউটার'],
            'Audio / Video' => ['Audio / Video', 'Audio / Video'],
            'শুধু বাংলা' => ['শুধু বাংলা', 'শুধু বাংলা']] as $stored => $names) {
            DB::table('categories')->where('id', 1)->update(['name' => $stored]);
            foreach (['en-US', 'bn-BD'] as $index => $locale) {
                app()->setLocale($locale);
                $monitor = $service->programme($initiative, ['q' => $names[1-$index]]);
                $this->assertCount(1, $monitor['codes']);
                $this->assertSame($names[$index], $monitor['codes'][0]['targets'][0]['name']);
                $this->assertSame($names[$index], $monitor['receipts'][0]['item']);
                $this->assertSame($names[$index], $monitor['items'][0]['name']);
            }
        }
    }

    public function test_monitor_table_has_product_counts_segmented_codes_and_single_month_activity(): void
    {
        $this->viewer();
        [$initiative, $code] = $this->programmeFixture();
        $code->update(['tracking_code' => 'EXP-shared-ADP-6']);
        $this->receipt($code);
        $other = $code->replicate();
        $other->tracking_code = 'EXP-shared-ADP-12';
        $other->save();
        $monitor = app(TrackingMonitorService::class)->programme($initiative);
        $html = view('govtracking::initiatives.monitor', compact('initiative', 'monitor'))->render();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($dom);
        $rows = $xpath->query('//table[.//button[@data-monitor-sort]]/tbody/tr');
        $this->assertCount(2, $rows);
        foreach ($rows as $row) {
            $cell = $xpath->query('./td[1]', $row)->item(0);
            $this->assertStringNotContainsString('Equipment', $cell->textContent);
            $this->assertSame(1, $xpath->query('.//span[@data-tone="muted"]', $cell)->length);
        }
        $this->assertStringContainsString(trans_choice('govtracking::general.monitor.product_count', 1), $rows->item(0)->textContent);
        $this->assertStringContainsString('data-sort="EXP-shared-ADP-6"', $html);
        $spark = view('govtracking::monitor.spark', ['code' => $monitor['codes'][0]])->render();
        $this->assertStringContainsString('<circle cx="50"', $spark);
        $this->assertStringContainsString(trans_choice('govtracking::general.monitor.receipt_count_short', 1).' · 2026-10', $spark);
        $this->assertStringNotContainsString('<polyline', $spark);
        $multiple = $monitor['codes'][0];
        $multiple['receipts'][] = array_replace($multiple['receipts'][0], ['date' => '2026-11-01', 'grn' => 'NEXT-MONTH']);
        $this->assertStringContainsString('<polyline', view('govtracking::monitor.spark', ['code' => $multiple])->render());
        $multiple['unknown_count'] = 1;
        $this->assertStringNotContainsString('<svg', view('govtracking::monitor.spark', ['code' => $multiple])->render());
    }
}
