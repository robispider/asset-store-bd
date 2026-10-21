<?php

namespace Tests\Feature\GovStore;

use App\Models\Asset;
use Carbon\CarbonImmutable;
use GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme;
use GovStore\Tracking\Listeners\AssociateInventoryToProgramme;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Repositories\CachedTrackingProjectionRepository;
use GovStore\Tracking\Services\TrackingMonitorMetrics;
use GovStore\Tracking\Services\TrackingReceiptResolver;
use Illuminate\Support\Facades\DB;

class TrackingCorrectnessTest extends TrackingWorkflowTest
{
    public function test_matrix_caps_each_cell_and_programme_weights_item_lines(): void
    {
        $m = new TrackingMonitorMetrics;
        $this->assertSame('2_CATEGORY', $m->plan('CATEGORY'));
        $this->assertSame('3_MATRIX', $m->plan('MATRIX'));
        $this->assertSame('unknown', $m->plan('not-a-plan'));
        $line = $m->line(10, 10, [['allocated' => 5, 'received' => 10], ['allocated' => 5, 'received' => 0]]);
        $this->assertSame(.5, $line['fulfilment']);
        $this->assertTrue($line['over']);
        $this->assertSame(.75, $m->mean([$line['fulfilment'], 1]));
        $this->assertNull($m->line(0, 10)['fulfilment']);
        $this->assertSame('waiting', $m->office('ACTIVE', 10, 2, 0, false));
    }

    public function test_fiscal_pace_health_forecast_boundaries_and_unknown_dates(): void
    {
        $m = new TrackingMonitorMetrics;
        $today = CarbonImmutable::parse('2026-10-18');
        $this->assertSame('2027-06-30', $m->fiscalEnd('2026-27')->toDateString());
        $this->assertNull($m->fiscalEnd('2026-2028'));
        $this->assertNull($m->expected(null, '2026-2027', $today));
        $this->assertNull($m->expected('2027-06-30', '2026-2027', $today));
        $this->assertEquals(0, $m->expected('2026-11-01', '2026-2027', $today));
        $this->assertEquals(1, $m->expected('2026-07-01', '2026-2027', CarbonImmutable::parse('2027-07-01')));
        $this->assertSame('draft', $m->health('DRAFT', true, 0, 1, '2026-07-01', null, $today));
        $this->assertSame('over_allocation', $m->health('ACTIVE', true, 0, 1, '2026-07-01', null, $today));
        $this->assertSame('stalled', $m->health('ACTIVE', false, 0, 1, '2026-07-01', null, $today));
        $this->assertSame('on_pace', $m->health('ACTIVE', false, .45, .5, '2026-09-18', null, $today));
        $this->assertSame('behind', $m->health('ACTIVE', false, .44, .5, '2026-09-18', null, $today));
        $this->assertNull($m->forecast(0, '2026-07-01', $today));
        $this->assertNull($m->forecast(.2, '2026-10-18', $today));
        $this->assertSame('2026-11-17', $m->forecast(.5, '2026-09-18', $today)->toDateString());
    }

    public function test_prices_use_weighted_average_and_zero_price_has_no_spread(): void
    {
        $m = new TrackingMonitorMetrics;
        $p = $m->prices([['quantity' => 1, 'value' => 10], ['quantity' => 3, 'value' => 90]]);
        $this->assertEquals(25, $p['average']);
        $this->assertEquals(.8, $p['spread']);
        $this->assertNull($m->prices([['quantity' => 3, 'value' => 0]])['spread']);
        $this->assertSame('৳12,345.50', $m::money(12345.5));
    }

    public function test_draft_and_archived_plans_do_not_inflate_lifecycle_and_cache_is_not_trusted(): void
    {
        $i = Initiative::withoutGlobalScopes()->create(['title' => 'Metrics', 'owner_company_id' => 20, 'primary_funding' => 'ADP', 'status' => 'Active']);
        $fund = DB::table('gov_funding_types')->insertGetId(['name' => 'Fund', 'primary_type' => 'ADP']);
        foreach (['ACTIVE', 'DRAFT', 'ARCHIVED'] as $status) {
            $code = TrackingCode::create(['initiative_id' => $i->id, 'funding_type_id' => $fund, 'tracking_code' => $status, 'task_title' => $status, 'specificity_level' => '2_CATEGORY', 'fiscal_year' => '2026-27', 'status' => $status]);
            DB::table('gov_tracking_targets')->insert(['tracking_code_id' => $code->id, 'category_id' => 1, 'planned_qty' => 10]);
            TrackingAssociation::create(['tracking_code_id' => $code->id, 'category_id' => 1, 'location_id' => 10, 'quantity' => 5, 'status' => 'ACTIVE', 'associatable_type' => 'test', 'associatable_id' => $code->id]);
        }
        DB::table('gov_tracking_projection_caches')->updateOrInsert(['tracking_reference_id' => $i->id], ['planned' => 1000, 'received' => 1000]);
        $r = app(CachedTrackingProjectionRepository::class)->getLifecycleSummary($i);
        $this->assertSame(10, $r['planned']);
        $this->assertSame(5, $r['received']);
        $this->assertEquals(50, $r['percentage']);
    }

    public function test_bulk_receipt_provenance_survives_replay_and_resolves_value_once(): void
    {
        $i = Initiative::withoutGlobalScopes()->create(['title' => 'Source', 'owner_company_id' => 20, 'primary_funding' => 'ADP', 'status' => 'Active']);
        $fund = DB::table('gov_funding_types')->insertGetId(['name' => 'Fund', 'primary_type' => 'ADP']);
        $code = TrackingCode::create(['initiative_id' => $i->id, 'funding_type_id' => $fund, 'tracking_code' => 'SOURCE', 'task_title' => 'Source', 'specificity_level' => '1_BLANKET', 'fiscal_year' => '2026-27', 'status' => 'ACTIVE']);
        $e = new InventoryMaterializedAgainstProgramme('SOURCE', 1, 0, 0, 10, 3, 90, 0, 1, 'GRN-PROVENANCE', [], null, 'receipt-line-source');
        $listener = app(AssociateInventoryToProgramme::class);
        $listener->handle($e);
        $listener->handle($e);
        $rows = app(TrackingReceiptResolver::class)->resolve(TrackingAssociation::where('tracking_code_id', $code->id)->get());
        $this->assertCount(1, $rows);
        $this->assertEquals(90, $rows[0]['value']);
        $this->assertEquals(3, $rows[0]['quantity']);
        $this->assertSame('GRN-PROVENANCE', $rows[0]['grn']);
        DB::table('gov_tracking_fact_deliveries')->where('tracking_code_id', $code->id)->update(['received_qty' => 6, 'total_cost' => 180]);
        $this->artisan('govstore:tracking-rebuild-facts', ['--code' => $code->id, '--apply' => true])->assertSuccessful();
        $this->artisan('govstore:tracking-rebuild-facts', ['--code' => $code->id, '--apply' => true])->assertSuccessful();
        $this->assertEquals(3, DB::table('gov_tracking_fact_deliveries')->sum('received_qty'));
        $this->assertEquals(90, DB::table('gov_tracking_fact_deliveries')->sum('total_cost'));
    }

    public function test_serialized_assets_share_one_line_value_and_partial_links_receive_only_their_share(): void
    {
        $i = Initiative::withoutGlobalScopes()->create(['title' => 'Serialized', 'owner_company_id' => 20, 'primary_funding' => 'ADP', 'status' => 'Active']);
        $fund = DB::table('gov_funding_types')->insertGetId(['name' => 'Fund', 'primary_type' => 'ADP']);
        $code = TrackingCode::create(['initiative_id' => $i->id, 'funding_type_id' => $fund, 'tracking_code' => 'SERIALIZED', 'task_title' => 'Serialized', 'specificity_level' => '1_BLANKET', 'fiscal_year' => '2026-27', 'status' => 'ACTIVE']);
        $assets = [];
        foreach ([1, 2, 3] as $id) {
            DB::table('assets')->insert(['id' => $id, 'model_id' => 1, 'status_id' => 1, 'location_id' => 10, 'company_id' => 20, 'asset_tag' => 'SER-'.$id]);
            $assets[] = ['type' => Asset::class, 'id' => $id];
        }
        $event = new InventoryMaterializedAgainstProgramme('SERIALIZED', 1, 1, 0, 10, 3, 90, 0, 1, 'GRN-SERIAL', $assets, null, 'serial-source');
        app(AssociateInventoryToProgramme::class)->handle($event);
        $associations = TrackingAssociation::where('tracking_code_id', $code->id)->get();
        $resolver = app(TrackingReceiptResolver::class);
        $rows = $resolver->resolve($associations);
        $this->assertCount(1, $rows);
        $this->assertEquals(3, $rows[0]['quantity']);
        $this->assertEquals(90, $rows[0]['value']);
        $this->assertEquals(30, $resolver->resolve($associations->take(1))[0]['value']);

        // Historical serialized associations have no delivery link; recover the exact posted intake line.
        DB::table('gov_documents')->insert(['id' => 'serial-receipt', 'document_number' => 'GRN-OLD-SERIAL', 'type' => 'receipt', 'status' => 'POSTED', 'created_by' => 1, 'location_id' => 10, 'company_id' => 20, 'posted_at' => '2026-10-01']);
        DB::table('gov_document_items')->insert(['id' => 'serial-line', 'document_id' => 'serial-receipt', 'product_type' => 'asset_model', 'product_id' => 1, 'quantity' => 3, 'unit_cost' => 30]);
        foreach ([1, 2, 3] as $id) {
            DB::table('gov_asset_registrations')->insert(['intake_item_id' => 'serial-line', 'asset_id' => $id, 'asset_tag' => 'SER-'.$id, 'created_at' => now()]);
        }
        TrackingAssociation::where('tracking_code_id', $code->id)->update(['delivery_id' => null]);
        $old = $resolver->resolve(TrackingAssociation::where('tracking_code_id', $code->id)->get());
        $this->assertCount(1, $old);
        $this->assertEquals(90, $old[0]['value']);
        $this->assertSame('serial-receipt', $old[0]['document_id']);
    }
}
