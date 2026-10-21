<?php

namespace Tests\Feature\GovStore;

use App\Models\Asset;
use App\Models\AssetModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use GovStore\StoreOperations\Models\Document;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingDelivery;
use GovStore\Tracking\Services\ProgrammeReportService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class TrackingProgrammeReportTest extends TrackingWorkflowTest
{
    protected function setUp(): void
    {
        parent::setUp();
        app()->setLocale('en-US');
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-19 14:00:00', config('app.timezone')));
        (require base_path('packages/gov-store/organization/src/database/migrations/2026_10_19_000001_add_office_level_to_location_profiles.php'))->up();
        Schema::table('gov_geo_areas', fn (Blueprint $t) => $t->string('geo_type')->nullable());
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->location_id = 10;
        $user->shouldReceive('isSuperUser')->andReturn(false);
        $user->shouldReceive('hasAccess')->andReturn(true); // Native admin still needs programme membership.
        auth()->setUser($user);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    private function programme(string $status = 'ACTIVE', int $company = 20): array
    {
        $i = Initiative::withoutGlobalScopes()->create(['title' => 'Report <programme>', 'owner_company_id' => $company, 'primary_funding' => 'ADP', 'status' => 'Active']);
        DB::table('gov_tracking_operation_units')->insert(['initiative_id' => $i->id, 'user_id' => 1, 'designation' => 'MONITOR']);
        $c = TrackingCode::create(['initiative_id' => $i->id, 'tracking_code' => 'REPORT-'.$i->id, 'task_title' => 'Report task',
            'specificity_level' => '2_CATEGORY', 'fiscal_year' => '2026-2027', 'status' => $status]);
        DB::table('gov_tracking_targets')->insert(['tracking_code_id' => $c->id, 'category_id' => 1, 'planned_qty' => 10]);

        return [$i, $c];
    }

    private function receipt(TrackingCode $code, int $location = 10, float $quantity = 2, ?float $cost = 12.5, string $status = 'POSTED', ?string $document = null, ?int $asset = null, int $category = 1): string
    {
        $document ??= (string) Str::uuid();
        if (! DB::table('gov_documents')->where('id', $document)->exists()) {
            DB::table('gov_documents')->insert(['id' => $document, 'document_number' => 'GRN-'.$document, 'type' => 'receipt', 'status' => $status,
                'location_id' => $location, 'company_id' => $location === 10 ? 20 : 21, 'created_by' => 1, 'posted_at' => '2026-10-15 10:00:00', 'created_at' => '2026-10-14']);
        }
        $item = (string) Str::uuid();
        DB::table('gov_document_items')->insert(['id' => $item, 'document_id' => $document, 'product_type' => 'assetmodel', 'product_id' => $category, 'quantity' => $quantity, 'unit_cost' => $cost]);
        $delivery = DB::table('gov_tracking_deliveries')->insertGetId(['tracking_code_id' => $code->id, 'delivery_key' => hash('sha256', $item),
            'document_item_id' => $item, 'created_at' => '2026-10-15']);
        DB::table('gov_tracking_associations')->insert(['tracking_code_id' => $code->id, 'delivery_id' => $delivery, 'category_id' => $category, 'location_id' => $location,
            'quantity' => $quantity, 'status' => 'ACTIVE', 'associatable_type' => $asset ? Asset::class : TrackingDelivery::class,
            'associatable_id' => $asset ?: $delivery, 'created_at' => '2026-10-15', 'updated_at' => '2026-10-15']);
        if ($asset) {
            DB::table('assets')->insert(['id' => $asset, 'model_id' => 1, 'status_id' => 1, 'location_id' => $location,
                'company_id' => $location === 10 ? 20 : 21, 'asset_tag' => 'PRIVATE-TAG-'.$asset]);
            DB::table('gov_asset_registrations')->insert(['asset_id' => $asset, 'intake_item_id' => $item, 'asset_tag' => 'PRIVATE-TAG-'.$asset, 'created_at' => '2026-10-15']);
        }

        return $document;
    }

    public function test_report_includes_archived_receipts_excludes_drafts_and_counts_documents_once(): void
    {
        [$i, $c] = $this->programme('ARCHIVED');
        $doc = $this->receipt($c);
        $this->receipt($c, 10, 3, null, 'POSTED', $doc);
        $this->receipt($c, 10, 50, 500, 'DRAFT');
        $draft = $c->replicate();
        $draft->tracking_code = 'DRAFT-REPORT';
        $draft->status = 'DRAFT';
        $draft->save();
        $this->receipt($draft, 10, 99);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(5, $r['totals']['received']);
        $this->assertEquals(25, $r['totals']['known_value']);
        $this->assertFalse($r['totals']['value_complete']);
        $this->assertSame(1, $r['totals']['grns']);
        $this->assertSame(1, $r['draft_count']);
        $this->assertEquals(50, $r['excluded_quantity']);
        $this->assertSame('ARCHIVED', $r['codes'][0]['status']);
        $this->assertEquals(5, $r['items'][0]['matrix']['unknown']);
        $this->assertSame('incomplete', collect($r['quality'])->firstWhere('key', 'receipts')['status']);
    }

    public function test_real_product_morph_keys_and_legacy_posted_dates_keep_receipts_and_flag_estimates(): void
    {
        [$i, $code] = $this->programme();
        foreach (['assetmodel', 'asset_model', AssetModel::class] as $type) {
            $doc = $this->receipt($code, 10, 2, 12.5);
            DB::table('gov_document_items')->where('document_id', $doc)->update(['product_type' => $type]);
            DB::table('gov_documents')->where('id', $doc)->update(['posted_at' => null]);
        }
        // The existing stock register supports short and class keys for bulk stock too.
        $bulk = $this->receipt($code, 10, 3, 2);
        DB::table('gov_document_items')->where('document_id', $bulk)->update(['product_type' => 'consumable', 'product_id' => DB::table('consumables')->value('id')]);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(9, $r['totals']['received']);
        $this->assertEquals(81, $r['totals']['known_value']);
        $this->assertSame(4, $r['totals']['grns']);
        $this->assertSame(3, $r['totals']['estimated_dates']);
        $this->assertEquals(0, $r['excluded_quantity']);
        $dateQuality = collect($r['quality'])->firstWhere('key', 'receipt_dates');
        $this->assertSame('incomplete', $dateQuality['status']);
        $this->assertEquals(1, $dateQuality['good']);
        // Being a legacy record does not excuse draft state or a future creation date.
        DB::table('gov_documents')->whereNull('posted_at')->update(['created_at' => '2026-10-20']);
        $this->assertEquals(3, app(ProgrammeReportService::class)->programme($i)['totals']['received']);
        DB::table('gov_documents')->where('id', $bulk)->update(['status' => 'DRAFT']);
        $this->assertEquals(0, app(ProgrammeReportService::class)->programme($i)['totals']['received']);
    }

    public function test_programme_access_is_required_on_each_direct_service_call(): void
    {
        [$i, $c] = $this->programme();
        $this->receipt($c);
        $service = app(ProgrammeReportService::class);
        $this->assertEquals(2, $service->programme($i)['totals']['received']);
        DB::table('gov_tracking_operation_units')->delete();
        $this->expectException(HttpExceptionInterface::class);
        $service->programme($i);
    }

    public function test_legacy_bulk_movement_keeps_its_exact_line_and_rejects_ambiguous_product_matches(): void
    {
        [$i, $code] = $this->programme();
        $doc = $this->receipt($code);
        DB::table('gov_document_items')->where('document_id', $doc)->update(['product_type' => 'asset_model']);
        $movement = (string) Str::uuid();
        DB::table('gov_inventory_movements')->insert(['id' => $movement, 'stockable_type' => AssetModel::class,
            'stockable_id' => 1, 'movement_type' => 'IN', 'quantity' => 2, 'document_type' => Document::class,
            'document_id' => $doc, 'location_id' => 10, 'company_id' => 20, 'created_by' => 1]);
        DB::table('gov_tracking_deliveries')->where('tracking_code_id', $code->id)->update(['document_item_id' => null, 'movement_id' => $movement]);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(2, $r['totals']['received']);
        $this->assertEquals(25, $r['totals']['known_value']);
        $line = (array) DB::table('gov_document_items')->where('document_id', $doc)->first();
        $line['id'] = (string) Str::uuid();
        DB::table('gov_document_items')->insert($line);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(0, $r['totals']['received']);
        $this->assertEquals(2, $r['excluded_quantity']);
    }

    public function test_split_receipt_values_are_rounded_after_aggregation(): void
    {
        [$i, $code] = $this->programme();
        $doc = $this->receipt($code, 10, .1, .05);
        $line = DB::table('gov_document_items')->where('document_id', $doc)->value('id');
        DB::table('gov_document_items')->where('id', $line)->update(['quantity' => .2]);
        $other = $code->replicate();
        $other->tracking_code = 'SPLIT-VALUE';
        $other->save();
        $delivery = DB::table('gov_tracking_deliveries')->insertGetId(['tracking_code_id' => $other->id, 'delivery_key' => hash('sha256', 'split:'.$line),
            'document_item_id' => $line, 'created_at' => '2026-10-15']);
        DB::table('gov_tracking_associations')->insert(['tracking_code_id' => $other->id, 'delivery_id' => $delivery, 'category_id' => 1, 'location_id' => 10,
            'quantity' => .1, 'status' => 'ACTIVE', 'associatable_type' => TrackingDelivery::class, 'associatable_id' => $delivery,
            'created_at' => '2026-10-15', 'updated_at' => '2026-10-15']);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(.2, $r['totals']['received']);
        $this->assertEquals(.01, $r['totals']['known_value']);
        $this->assertEquals(.01, $r['items'][0]['known_value']);
        $this->assertSame(1, $r['totals']['grns']);
    }

    public function test_missing_asset_status_is_unknown_and_negative_cost_is_not_a_known_price(): void
    {
        [$i, $code] = $this->programme();
        $this->receipt($code, 10, 1, 10, 'POSTED', null, 101);
        $this->receipt($code, 10, 1, -5, 'POSTED', null, 102);
        $this->receipt($code, 10, 1, 0, 'POSTED', null, 103);
        DB::table('assets')->where('id', 101)->update(['status_id' => 999]);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(1, $r['totals']['unknown']);
        $this->assertEquals(0, $r['totals']['other']);
        $this->assertEquals(2, $r['totals']['store']);
        $this->assertEquals(10, $r['totals']['known_value']);
        $this->assertFalse($r['totals']['value_complete']);
        $this->assertEquals(0, $r['items'][0]['price_min']);
        $this->assertEquals(10, $r['items'][0]['price_max']);
    }

    public function test_report_rejects_snapshot_only_future_wrong_category_and_overattributed_receipts(): void
    {
        [$i, $c] = $this->programme();
        $this->receipt($c, 10, 1.25, 10.2);
        $future = $this->receipt($c, 10, 9);
        DB::table('gov_documents')->where('id', $future)->update(['posted_at' => '2026-10-20']);
        $wrong = $this->receipt($c, 10, 7);
        $delivery = DB::table('gov_tracking_deliveries')->join('gov_document_items', 'gov_document_items.id', '=', 'gov_tracking_deliveries.document_item_id')
            ->where('gov_document_items.document_id', $wrong)->value('gov_tracking_deliveries.id');
        DB::table('gov_tracking_associations')->where('delivery_id', $delivery)->update(['category_id' => 999]);
        $over = $this->receipt($c, 10, 2);
        DB::table('gov_document_items')->where('document_id', $over)->update(['quantity' => 1]);
        DB::table('gov_tracking_deliveries')->where('id', $delivery)->update(['receipt_snapshot' => json_encode(['quantity' => 7, 'total_cost' => 99999,
            'category_id' => 999, 'location_id' => 10, 'grn' => 'SNAPSHOT', 'occurred_at' => '2026-10-01'])]);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(1.25, $r['totals']['received']);
        $this->assertEquals(12.75, $r['totals']['known_value']);
        $this->assertEquals(18, $r['excluded_quantity']);
        $this->assertSame(1, $r['totals']['grns']);
        // A snapshot cannot override the authoritative posted line's unit cost.
        DB::table('gov_tracking_deliveries')->where('tracking_code_id', $c->id)->update(['receipt_snapshot' => json_encode([
            'quantity' => 1.25, 'total_cost' => 99999, 'category_id' => 1, 'location_id' => 10])]);
        $this->assertEquals(12.75, app(ProgrammeReportService::class)->programme($i)['totals']['known_value']);
    }

    public function test_blanket_missing_and_zero_targets_do_not_invent_a_denominator_or_lose_untargeted_items(): void
    {
        [$i, $c] = $this->programme();
        $c->update(['specificity_level' => '1_BLANKET']);
        DB::table('gov_tracking_targets')->delete();
        $this->receipt($c, 11, 3);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertNull($r['codes'][0]['planned']);
        $this->assertNull($r['items'][0]['planned']);
        $this->assertEquals(3, $r['totals']['received']);
        $this->assertCount(1, $r['offices']);
        $this->assertSame('na', collect($r['quality'])->firstWhere('key', 'targets')['status']);
    }

    public function test_company_admin_cannot_read_foreign_programme_even_with_an_allowed_receiving_office(): void
    {
        [$own, $code] = $this->programme();
        [$foreign, $other] = $this->programme('ACTIVE', 21);
        $this->receipt($code, 11, 2);
        $this->receipt($other, 10, 100);
        DB::table('gov_tracking_operation_units')->delete();
        DB::table('gov_company_admins')->insert(['user_id' => 1, 'company_id' => 20]);
        $this->assertEquals(2, app(ProgrammeReportService::class)->programme($own)['totals']['received']);
        $this->expectException(HttpExceptionInterface::class);
        app(ProgrammeReportService::class)->programme($foreign);
    }

    public function test_matrix_plan_uses_allocations_and_mixed_blanket_actuals_do_not_inflate_item_progress(): void
    {
        [$i, $c] = $this->programme();
        $c->update(['specificity_level' => '3_MATRIX']);
        $target = DB::table('gov_tracking_targets')->where('tracking_code_id', $c->id)->value('id');
        DB::table('gov_tracking_targets')->where('id', $target)->update(['planned_qty' => 999]);
        DB::table('gov_tracking_allocations')->insert(['target_id' => $target, 'location_id' => 10, 'allocated_qty' => 3]);
        $this->receipt($c);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(3, $r['codes'][0]['planned']);
        $this->assertEquals(3, $r['items'][0]['planned']);
        $blanket = $c->replicate();
        $blanket->tracking_code = 'BLANKET-REPORT';
        $blanket->specificity_level = '1_BLANKET';
        $blanket->save();
        $this->receipt($blanket, 10, 3);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(5, $r['items'][0]['received']);
        $this->assertNull($r['items'][0]['planned']);
    }

    public function test_utilisation_respects_inventory_scope_and_transfer_state_without_moving_receipt_destination(): void
    {
        [$i, $c] = $this->programme();
        $this->receipt($c, 10, 1, 10, 'POSTED', null, 101);
        $this->receipt($c, 10, 1, 10, 'POSTED', null, 102);
        $this->receipt($c, 11, 1, 10, 'POSTED', null, 103);
        DB::table('assets')->where('id', 101)->update(['assigned_to' => 1]);
        DB::table('assets')->where('id', 102)->update(['location_id' => 11, 'company_id' => 21]);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(3, $r['totals']['received']);
        $this->assertEquals(1, $r['totals']['used']);
        $this->assertEquals(0, $r['totals']['store']);
        $this->assertEquals(2, $r['totals']['unknown']);
        $context = app(TenantContext::class);
        $context->allowedInventoryLocationIds = [10, 11];
        $context->allowedInventoryCompanyIds = [20, 21];
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(1, $r['totals']['other']);
        $this->assertEquals(1, $r['totals']['store']);
        $this->assertEquals(0, $r['totals']['unknown']);
        $this->assertEquals(2, collect($r['offices'])->firstWhere('id', 10)['received']);
        $this->assertStringNotContainsString('PRIVATE-TAG', json_encode($r));
        $this->assertStringNotContainsString('assigned_to', json_encode($r));
    }

    public function test_a_serialized_asset_linked_to_two_codes_is_excluded_instead_of_counted_twice(): void
    {
        [$i, $c] = $this->programme();
        $this->receipt($c, 10, 1, 10, 'POSTED', null, 101);
        $other = $c->replicate();
        $other->tracking_code = 'DUPLICATE-SOURCE';
        $other->save();
        $link = (array) DB::table('gov_tracking_associations')->first();
        unset($link['id']);
        $link['tracking_code_id'] = $other->id;
        $link['delivery_id'] = null;
        DB::table('gov_tracking_associations')->insert($link);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(0, $r['totals']['received']);
        $this->assertEquals(2, $r['excluded_quantity']);
        $this->assertSame('incomplete', collect($r['quality'])->firstWhere('key', 'receipts')['status']);
    }

    public function test_quarantined_asset_cannot_inflate_usage_of_another_asset_on_the_same_delivery(): void
    {
        [$i, $c] = $this->programme();
        $this->receipt($c, 10, 1, 10, 'POSTED', null, 101);
        $this->receipt($c, 10, 1, 10, 'POSTED', null, 102);
        $first = DB::table('gov_tracking_associations')->where('associatable_id', 101)->first();
        $line = DB::table('gov_tracking_deliveries')->where('id', $first->delivery_id)->value('document_item_id');
        DB::table('gov_document_items')->where('id', $line)->update(['quantity' => 2]);
        DB::table('gov_asset_registrations')->where('asset_id', 102)->update(['intake_item_id' => $line]);
        DB::table('gov_tracking_associations')->where('associatable_id', 102)->update(['delivery_id' => $first->delivery_id]);
        $other = $c->replicate();
        $other->tracking_code = 'DUPLICATE-MIXED';
        $other->save();
        $link = (array) $first;
        unset($link['id']);
        $link['tracking_code_id'] = $other->id;
        $link['delivery_id'] = null;
        DB::table('gov_tracking_associations')->insert($link);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertEquals(1, $r['totals']['received']);
        $this->assertEquals(1, $r['totals']['store']);
        $this->assertEquals(0, $r['totals']['unknown']);
    }

    public function test_levels_geographic_subtotals_and_missing_areas_retain_every_office(): void
    {
        [$i, $c] = $this->programme();
        DB::table('gov_geo_areas')->insert([
            ['GeoAreaId' => 1, 'hid' => '/1/', 'geo_type' => 'divison', 'en_name' => 'Division', 'bn_name' => 'বিভাগ'],
            ['GeoAreaId' => 2, 'hid' => '/1/2/', 'geo_type' => 'district', 'en_name' => 'District', 'bn_name' => 'জেলা'],
            ['GeoAreaId' => 3, 'hid' => '/1/2/3/', 'geo_type' => 'upazilla', 'en_name' => 'Upazila', 'bn_name' => 'উপজেলা'],
        ]);
        DB::table('gov_location_profiles')->insert(['location_id' => 10, 'geo_area_id' => 3, 'office_type' => 'school']);
        $this->receipt($c, 10);
        $this->receipt($c, 11, 3);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertSame(1, $r['inferred_levels']);
        $this->assertSame(1, $r['unknown_levels']);
        $this->assertCount(2, array_filter($r['hierarchy'], fn ($row) => ! $row['band']));
        $this->assertEquals(2, collect($r['hierarchy'])->firstWhere('name', 'District · District subtotal')['received']);
        $this->assertSame(['value' => 'regional', 'source' => 'explicit'], ProgrammeReportService::level('regional', 'district'));
        $this->assertSame('unknown', ProgrammeReportService::level(null, 'city')['value']);
        DB::table('gov_location_profiles')->update(['office_level' => 'headquarters']);
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertArrayHasKey('headquarters', $r['levels']);
    }

    public function test_report_renders_both_languages_escapes_names_and_has_translation_parity(): void
    {
        [$i, $c] = $this->programme();
        $this->receipt($c);
        $dir = storage_path('framework/testing/tracking-report/layouts');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/default.blade.php', '@yield("content")');
        app('view')->getFinder()->prependLocation(dirname($dir));
        app('view')->getDispatcher()->forget('composing: layouts.default');
        try {
            foreach (['en-US', 'bn-BD'] as $locale) {
                app()->setLocale($locale);
                $html = view('govtracking::initiatives.report', ['initiative' => $i, 'report' => app(ProgrammeReportService::class)->programme($i)])->render();
                $this->assertStringContainsString('Report &lt;programme&gt;', $html);
                $this->assertStringContainsString('data-report-section="quality"', $html);
                $this->assertStringNotContainsString('govtracking::report.', $html);
                $this->assertStringNotContainsString('PRIVATE-TAG', $html);
            }
        } finally {
            unlink($dir.'/default.blade.php');
        }
        $en = require base_path('packages/gov-store/tracking/src/resources/lang/en-US/report.php');
        $bn = require base_path('packages/gov-store/tracking/src/resources/lang/bn-BD/report.php');
        $this->assertSame(array_keys(Arr::dot($en)), array_keys(Arr::dot($bn)));
    }

    public function test_large_office_report_has_bounded_queries_and_conserves_totals(): void
    {
        [$i, $c] = $this->programme();
        DB::table('gov_geo_areas')->insert([
            ['GeoAreaId' => 1, 'hid' => '/1/', 'geo_type' => 'division', 'en_name' => 'Division', 'bn_name' => 'বিভাগ'],
            ['GeoAreaId' => 2, 'hid' => '/1/2/', 'geo_type' => 'district', 'en_name' => 'District', 'bn_name' => 'জেলা'],
            ['GeoAreaId' => 3, 'hid' => '/1/2/3/', 'geo_type' => 'upazilla', 'en_name' => 'Upazila', 'bn_name' => 'উপজেলা'],
        ]);
        $dir = storage_path('framework/testing/tracking-report/layouts');
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        file_put_contents($dir.'/default.blade.php', '@yield("content")');
        app('view')->getFinder()->prependLocation(dirname($dir));
        app('view')->getDispatcher()->forget('composing: layouts.default');
        try {
            foreach ([4, 1000] as $scale) {
                $start = $scale === 4 ? 1 : 5;
                for ($k = $start; $k <= $scale; $k++) {
                    $id = 1000 + $k;
                    DB::table('locations')->insert(['id' => $id, 'name' => 'Illustrative office '.$k.' — দীর্ঘ নামের পরীক্ষামূলক কার্যালয়', 'company_id' => 20]);
                    DB::table('gov_location_profiles')->insert(['location_id' => $id, 'geo_area_id' => 3,
                        'office_level' => $k % 2 ? 'district' : null]);
                    $this->receipt($c, $id, 2, 12.5);
                }
                DB::enableQueryLog();
                DB::flushQueryLog();
                $began = microtime(true);
                $r = app(ProgrammeReportService::class)->programme($i);
                $elapsed = microtime(true) - $began;
                $queries = count(DB::getQueryLog());
                DB::disableQueryLog();
                $this->assertLessThan(50, $queries, 'Query count must not grow with offices.');
                $this->assertCount($scale, $r['offices']);
                $this->assertEquals(2 * $scale, $r['totals']['received']);
                $this->assertEquals(25 * $scale, $r['totals']['known_value']);
                $this->assertEquals(2 * $scale, collect($r['hierarchy'])->where('band', false)->sum('received'));
                if (getenv('GOV_REPORT_PREVIEW') === '1') {
                    $this->preview($i, $scale, $queries, $elapsed);
                }
            }
        } finally {
            unlink($dir.'/default.blade.php');
        }
    }

    public function test_large_code_and_item_tables_use_the_same_report_contract(): void
    {
        [$i, $c] = $this->programme();
        for ($id = 2; $id <= 120; $id++) {
            DB::table('categories')->insert(['id' => $id, 'name' => 'Illustrative equipment '.$id.' / পরীক্ষামূলক দীর্ঘ নামের পণ্য '.$id]);
            DB::table('models')->insert(['id' => $id, 'name' => 'Model '.$id, 'category_id' => $id]);
            $code = TrackingCode::create(['initiative_id' => $i->id, 'tracking_code' => 'LONG-REPORT-CODE-'.$id,
                'task_title' => 'Illustrative code', 'specificity_level' => '2_CATEGORY', 'fiscal_year' => '2026-2027', 'status' => 'ACTIVE']);
            DB::table('gov_tracking_targets')->insert(['tracking_code_id' => $code->id, 'category_id' => $id, 'planned_qty' => 10]);
            $this->receipt($code, 10, 2, 12.5, 'POSTED', null, null, $id);
        }
        $r = app(ProgrammeReportService::class)->programme($i);
        $this->assertCount(120, $r['codes']);
        $this->assertCount(120, $r['items']);
        $this->assertEquals(238, $r['totals']['received']);
        if (getenv('GOV_REPORT_PREVIEW') === '1') {
            $dir = storage_path('framework/testing/tracking-report/layouts');
            if (! is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            file_put_contents($dir.'/default.blade.php', '@yield("content")');
            app('view')->getFinder()->prependLocation(dirname($dir));
            app('view')->getDispatcher()->forget('composing: layouts.default');
            try {
                $this->preview($i, 120, 0, 0);
            } finally {
                unlink($dir.'/default.blade.php');
            }
        }
    }

    /** Optional synthetic browser fixtures; never reads or writes the development database. */
    private function preview(Initiative $initiative, int $scale, int $queries, float $elapsed): void
    {
        $path = storage_path('framework/testing/tracking-report-preview');
        if (! is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $manifest = json_decode(file_get_contents(public_path('vendor/gs-theme/manifest.json')), true);
        copy(base_path('packages/gov-store/tracking/src/resources/js/report.js'), $path.'/report.js');
        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale);
            $r = app(ProgrammeReportService::class)->programme($initiative);
            $body = view('govtracking::initiatives.report', ['initiative' => $initiative, 'report' => $r])->render();
            foreach (['institutional-green' => 'light', 'digital-blue' => 'dark', 'default' => 'light'] as $theme => $mode) {
                $links = '';
                foreach (['kit', 'packages'] as $bundle) {
                    $links .= '<link rel="stylesheet" href="http://snipeit.local/vendor/gs-theme/'.$manifest[$bundle].'">';
                }
                $links .= '<link rel="stylesheet" href="http://snipeit.local/vendor/gs-theme/'.$manifest['themes'][$theme].'">';
                $html = '<!doctype html><html lang="'.$locale.'" data-skin="'.$theme.'" data-theme="'.$mode.'"><meta charset="utf-8"><title>Synthetic report preview</title><link rel="stylesheet" href="http://snipeit.local/css/dist/all.css">'.$links.'<body>'.$body.'<script src="report.js"></script></body></html>';
                file_put_contents($path.'/'.$scale.'-'.$locale.'-'.$theme.'.html', $html);
            }
        }
        file_put_contents($path.'/metrics-'.$scale.'.json', json_encode(['offices' => count($r['offices']), 'codes' => count($r['codes']), 'items' => count($r['items']), 'queries' => $queries, 'seconds' => round($elapsed, 3)]));
    }
}
