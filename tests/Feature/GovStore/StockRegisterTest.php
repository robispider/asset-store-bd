<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use Carbon\Carbon;
use GovStore\StoreOperations\Http\Controllers\StockRegisterController;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\LedgerPostingService;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\AccessDecision;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The redesigned stock register, stock card, asset card and printed copy (design canvas 6KvHsJKoEuDxDJr5zajYRv).
 * Isolated SQLite and a stub layout; dates are fixed so the financial-year arithmetic is exact.
 */
class StockRegisterTest extends StoreOperationsWorkflowTest
{
    private bool $posted = false;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::table('consumables', fn (Blueprint $t) => $t->integer('min_amt')->nullable());
        foreach (['accessories', 'components'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->increments('id'); $t->string('name'); $t->string('model_number')->nullable(); $t->integer('category_id')->nullable();
                $t->integer('qty')->default(0); $t->integer('min_amt')->nullable(); $t->integer('location_id'); $t->integer('company_id')->nullable();
                $t->timestamp('deleted_at')->nullable(); $t->timestamps();
            });
        }
        foreach (['categories', 'manufacturers', 'suppliers', 'companies'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamp('deleted_at')->nullable(); });
        }
        Schema::create('locations', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->integer('company_id')->nullable(); $t->string('address')->nullable(); $t->string('city')->nullable();
        });
        Schema::create('models', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->string('model_number')->nullable(); $t->integer('min_amt')->nullable(); $t->integer('category_id')->nullable();
            $t->integer('manufacturer_id')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('status_labels', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->boolean('deployable'); $t->boolean('pending'); $t->boolean('archived'); $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('first_name'); $t->string('last_name')->nullable(); $t->string('username'); $t->string('jobtitle')->nullable(); $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->increments('id'); $t->string('asset_tag'); $t->string('serial')->nullable(); $t->integer('model_id'); $t->integer('status_id');
            $t->integer('location_id')->nullable(); $t->integer('company_id')->nullable(); $t->integer('assigned_to')->nullable();
            $t->string('assigned_type')->nullable(); $t->integer('warranty_months')->nullable(); $t->date('purchase_date')->nullable();
            $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        (require base_path('packages/gov-store/store-operations/src/Database/migrations/2024_03_05_000000_create_gov_asset_registrations_table.php'))->up();

        DB::table('companies')->insert(['id' => 20, 'name' => 'Ministry of Test']);
        DB::table('locations')->insert([['id' => 10, 'name' => 'Central Store', 'company_id' => 20, 'address' => 'Secretariat', 'city' => 'Dhaka'],
            ['id' => 11, 'name' => 'Branch Store B', 'company_id' => 20, 'address' => null, 'city' => null]]);
        DB::table('categories')->insert([['id' => 1, 'name' => 'Stationery'], ['id' => 3, 'name' => 'Laptops']]);
        DB::table('manufacturers')->insert(['id' => 4, 'name' => 'Dell']);
        DB::table('suppliers')->insert(['id' => 7, 'name' => 'Sample Supplier Ltd.']);
        DB::table('consumables')->where('id', 1)->update(['item_no' => 'CON-0012', 'category_id' => 1, 'min_amt' => 15]);
        DB::table('consumables')->insert(['id' => 2, 'name' => 'Foreign paper', 'qty' => 9, 'location_id' => 11, 'company_id' => 20]);
        DB::table('accessories')->insert(['id' => 3, 'name' => 'USB keyboard', 'model_number' => 'ACC-0004', 'min_amt' => 5, 'location_id' => 10, 'company_id' => 20]);
        DB::table('components')->insert(['id' => 4, 'name' => 'Power supply 450 W', 'model_number' => 'CMP-0010', 'min_amt' => 2, 'location_id' => 10, 'company_id' => 20]);
        DB::table('models')->insert([
            ['id' => 5, 'name' => 'Latitude 5440', 'model_number' => 'MDL-0031', 'min_amt' => 1, 'category_id' => 3, 'manufacturer_id' => 4],
            ['id' => 6, 'name' => 'Other office printer', 'model_number' => null, 'min_amt' => null, 'category_id' => 3, 'manufacturer_id' => 4],
        ]);
        DB::table('status_labels')->insert([
            ['id' => 1, 'name' => 'Ready to deploy', 'deployable' => 1, 'pending' => 0, 'archived' => 0],
            ['id' => 2, 'name' => 'Out for repair', 'deployable' => 0, 'pending' => 0, 'archived' => 0],
        ]);
        DB::table('users')->insert([['id' => 1, 'first_name' => 'Nasrin', 'last_name' => 'Akter', 'username' => 'nasrin', 'jobtitle' => 'Store Officer'],
            ['id' => 2, 'first_name' => 'Kamal', 'last_name' => 'Hossain', 'username' => 'kamal', 'jobtitle' => 'Storekeeper']]);

        $dir = sys_get_temp_dir().'/storeops-register-layout';
        @mkdir($dir.'/layouts', 0777, true);
        file_put_contents($dir.'/layouts/default.blade.php', "@yield('content')");
        app('view')->getFinder()->prependLocation($dir);
        app('view')->getDispatcher()->forget('composing: layouts.default');
        view()->share('errors', new ViewErrorBag);
        app()->setLocale('en-US');
        $this->abilities(true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app()->setLocale('en-US');
        parent::tearDown();
    }

    private function abilities(bool $draft): void
    {
        auth()->setUser(User::withoutGlobalScopes()->findOrFail(2));
        app()->instance(GovAccess::class, Mockery::mock(GovAccess::class)->shouldReceive('decide')
            ->andReturnUsing(fn ($user, $ability) => new AccessDecision($draft && $ability !== 'storeops.documents.post', $ability, 'test'))->getMock());
    }

    /**
     * Office 10, FY from 1 Jul 2026. Paper: opening 12 (June), +24 receipt, −20 issue, −2 damage → 14 (reorder 15).
     * Power supply: +2 then −2 → out of stock. Laptop model: 3 units received, 1 issued → 2. Office 11 has its own rows.
     */
    private function ledger(): void
    {
        if ($this->posted) {
            return;
        }
        $this->posted = true;
        $ledger = app(LedgerPostingService::class);
        $at = fn (string $time) => Carbon::setTestNow(Carbon::parse($time));

        $at('2026-06-20 09:00');
        foreach ([10, 11] as $office) {
            $opening = $this->doc('opening', 'OB-'.$office, $office);
            DB::table('gov_store_ledger_openings')->insert(['location_id' => $office, 'document_id' => $opening->id,
                'opened_at' => now(), 'opened_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
        }
        $ledger->postMovement('consumable', 1, 'IN', 12, Document::withoutGlobalScopes()->where('document_number', 'OB-10')->first(), 20, 10, 1);

        $at('2026-07-06 10:14');
        $receipt = $this->doc('receipt', 'GR-2026-0031', 10, ['supplier_id' => 7, 'drafted_by' => 2, 'posted_by' => 1]);
        $receipt->references()->create(['reference_type' => 'Work order', 'reference_number' => 'WO-112/2026']);
        $ledger->postMovement('consumable', 1, 'IN', 24, $receipt, 20, 10, 1);

        $at('2026-07-20 11:05');
        $issue = $this->doc('issue', 'GI-2026-0102', 10, ['issued_to_user_id' => 2, 'issue_department' => 'Accounts Section', 'drafted_by' => 2, 'posted_by' => 1]);
        $issue->timelines()->create(['state' => 'DRAFT', 'user_id' => 2]);
        $issue->timelines()->create(['state' => 'POSTED', 'user_id' => 1]);
        $ledger->postMovement('consumable', 1, 'OUT', 20, $issue, 20, 10, 1);

        $at('2026-08-25 12:31');
        $adjustment = $this->doc('adjustment', 'ADJ-2026-0007', 10, ['adjustment_reason' => 'DAMAGE', 'source_document_id' => $receipt->id, 'drafted_by' => 1, 'posted_by' => 1]);
        $ledger->postMovement('consumable', 1, 'OUT', 2, $adjustment, 20, 10, 1);

        $at('2026-09-01 09:00');
        $ledger->postMovement('component', 4, 'IN', 2, $receipt, 20, 10, 1);
        $at('2026-09-02 09:00');
        $ledger->postMovement('component', 4, 'OUT', 2, $issue, 20, 10, 1);

        $at('2026-09-22 12:48');
        $assets = $this->doc('receipt', 'GR-2026-0058', 10, ['supplier_id' => 7, 'drafted_by' => 2, 'posted_by' => 1]);
        $line = $assets->items()->create(['product_type' => 'assetmodel', 'product_id' => 5, 'quantity' => 3]);
        $ledger->postMovement('assetmodel', 5, 'IN', 3, $assets, 20, 10, 1);
        foreach ([[101, 'AST-0412', 'SN-A1', 1, null], [102, 'AST-0413', 'SN-A2', 1, 2], [103, 'AST-0414', 'SN-A3', 2, null]] as [$id, $tag, $serial, $status, $holder]) {
            DB::table('assets')->insert(['id' => $id, 'asset_tag' => $tag, 'serial' => $serial, 'model_id' => 5, 'status_id' => $status,
                'location_id' => 10, 'company_id' => 20, 'assigned_to' => $holder, 'assigned_type' => $holder ? User::class : null,
                'warranty_months' => 36, 'purchase_date' => '2026-09-22']);
            DB::table('gov_asset_registrations')->insert(['intake_item_id' => $line->id, 'asset_id' => $id, 'asset_tag' => $tag, 'serial_number' => $serial, 'created_at' => now()]);
        }
        $at('2026-10-13 10:26');
        $ledger->postMovement('assetmodel', 5, 'OUT', 1, $this->doc('issue', 'GI-2026-0212', 10, ['issued_to_user_id' => 2, 'posted_by' => 1]), 20, 10, 1);

        $foreign = $this->doc('receipt', 'GR-OTHER-0001', 11);
        $ledger->postMovement('assetmodel', 5, 'IN', 7, $foreign, 20, 11, 1);
        $ledger->postMovement('assetmodel', 6, 'IN', 2, $foreign, 20, 11, 1);
        $ledger->postMovement('consumable', 2, 'IN', 9, $foreign, 20, 11, 1);

        $at('2026-10-15 14:02');
    }

    private function doc(string $type, string $number, int $office, array $extra = []): Document
    {
        return Document::withoutGlobalScopes()->create(array_merge(['document_number' => $number, 'type' => $type, 'status' => 'POSTED',
            'company_id' => 20, 'location_id' => $office, 'created_by' => 1, 'posted_at' => now()], $extra));
    }

    private function controller(): StockRegisterController
    {
        $this->ledger();

        return app(StockRegisterController::class);
    }

    private function req(string $uri, array $query = []): Request
    {
        return Request::create($uri, 'GET', $query);
    }

    private function card(string $type, int $id, array $query = [])
    {
        $request = $this->req('/gov-store/operations/kardex/'.$type.'/'.$id, $query);
        app()->instance('request', $request);

        return $this->controller()->kardex($request, $type, $id);
    }

    public function test_register_is_one_office_list_ranked_by_attention_with_ledger_balances(): void
    {
        $view = $this->controller()->index($this->req('/gov-store/operations/register'));
        $data = $view->getData();
        $rows = collect($data['items']->items());

        $this->assertSame(['Power supply 450 W', 'Paper', 'Latitude 5440', 'USB keyboard'], $rows->pluck('name')->all());
        $this->assertSame(['out', 'low', 'ok', 'none'], $rows->pluck('status')->all());
        $this->assertSame([0, 14, 2, null], $rows->pluck('balance')->all());
        $this->assertSame(['all' => 4, 'attention' => 2, 'low' => 1, 'out' => 1], array_intersect_key($data['counts'], array_flip(['all', 'attention', 'low', 'out'])));
        $this->assertSame(2, $data['counts']['asset_units']);
        $this->assertSame('ADJ-2026-0007', $rows->firstWhere('name', 'Paper')['last_document']);

        $html = $view->render();
        $this->assertStringNotContainsString('<x-gs', $html);
        foreach (['Central Store', 'Ledger opened 20 Jun 2026', 'You: view &amp; draft', 'Needs attention first', 'Reorder at 15',
            route('storeops.register.kardex', ['type' => 'assetmodel', 'id' => 5]), route('storeops.register.export'), 'How the ledger balance is calculated'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        // The native quantity (Paper qty 20, foreign items) never reaches store staff.
        foreach (['Foreign paper', 'Other office printer', 'Projection', 'Snipe-IT'] as $absent) {
            $this->assertStringNotContainsStringIgnoringCase($absent, $html);
        }
    }

    public function test_register_filters_search_and_sort(): void
    {
        $names = fn (array $query) => collect($this->controller()->index($this->req('/gov-store/operations/register', $query))->getData()['items']->items())->pluck('name')->all();

        $this->assertSame(['Power supply 450 W'], $names(['status' => 'out']));
        $this->assertSame(['Power supply 450 W', 'Paper'], $names(['status' => 'attention']));
        $this->assertSame(['Latitude 5440'], $names(['type' => 'assetmodel']));
        $this->assertSame(['Paper'], $names(['q' => 'con-0012']));
        $this->assertSame(['Latitude 5440', 'Paper', 'Power supply 450 W', 'USB keyboard'], $names(['sort' => 'name']));
        $this->assertSame(['Power supply 450 W', 'Latitude 5440', 'Paper', 'USB keyboard'], $names(['sort' => 'balance']));
        $this->assertSame(['Latitude 5440', 'Power supply 450 W', 'Paper', 'USB keyboard'], $names(['sort' => 'recent']));
        $this->assertSame([], $names(['q' => 'nothing like this']));
    }

    public function test_register_export_is_csv_of_the_filtered_list(): void
    {
        $response = $this->controller()->export($this->req('/gov-store/operations/register/export', ['status' => 'attention']));
        ob_start(); $response->sendContent(); $csv = ob_get_clean();
        $lines = array_map('str_getcsv', array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF"))));

        $this->assertSame('Item', $lines[0][0]);
        $this->assertSame(['Power supply 450 W', 'Paper'], [$lines[1][0], $lines[2][0]]);
        $this->assertSame('14', $lines[2][4]);
        $this->assertCount(3, $lines);
    }

    public function test_register_without_working_office_or_opening(): void
    {
        app(TenantContext::class)->locationId = null;
        $html = $this->controller()->index($this->req('/gov-store/operations/register'))->render();
        $this->assertStringContainsString(e(__('storeops::storeops.stock_register.no_office')), $html);

        app(TenantContext::class)->locationId = 10;
        DB::table('gov_store_ledger_openings')->where('location_id', 10)->delete();
        $html = $this->controller()->index($this->req('/gov-store/operations/register'))->render();
        $this->assertStringContainsString(e(__('storeops::storeops.stock_register.not_opened_title')), $html);
    }

    public function test_stock_card_shows_fy_brought_forward_totals_chain_and_entry_details(): void
    {
        $view = $this->card('consumable', 1);
        $this->assertSame('storeops::register.kardex', $view->name());
        $card = $view->getData()['card'];

        $this->assertSame([12, 24, 22, 14, 14], [$card['bf'], $card['in'], $card['out'], $card['closing'], $card['closing_all']]);
        $this->assertSame('fy', $card['period']->key);
        $this->assertSame('2026-07-01', $card['period']->start->toDateString());
        $this->assertTrue($card['chain']['ok']);
        $this->assertSame(4, $card['chain']['entries']);
        $this->assertSame(['GR-2026-0031', 'GI-2026-0102', 'ADJ-2026-0007'], collect($card['entries']->items())->pluck('document_number')->all());
        $this->assertSame([36, 16, 14], collect($card['entries']->items())->pluck('balance')->all());
        $this->assertSame(['receipt', 'issue', 'adjustment'], collect($card['entries']->items())->pluck('kind')->all());
        $this->assertSame('reorder', $card['outlook']['state']);
        $this->assertSame(20, $card['outlook']['issued_90']);

        $issue = collect($card['entries']->items())->firstWhere('document_number', 'GI-2026-0102');
        $this->assertSame('Kamal Hossain · Accounts Section', $issue['party']);
        $this->assertSame(['name' => 'Kamal Hossain', 'role' => 'Storekeeper'], $issue['drafter']);
        $this->assertFalse($issue['same_person']);
        $this->assertSame('ADJ-2026-0007', $card['selected']['document_number']);
        $this->assertTrue($card['selected']['same_person']);
        $this->assertSame('GR-2026-0031', $card['selected']['corrects']);

        $html = $view->render();
        $this->assertStringNotContainsString('<x-gs', $html);
        foreach (['Balance brought forward', 'Period totals and closing balance', 'Ledger chain verified', 'Supplier: <strong>Sample Supplier Ltd.</strong>',
            'Work order WO-112/2026', 'Reason: <strong>Damage</strong>', 'Corrects GR-2026-0031', 'Drafted and posted by the same person',
            'Correct with an adjustment', 'Reorder now', 'storeops-chart__line', 'Below reorder level', 'Item code <strong>CON-0012</strong>',
            route('storeops.register.kardex.print', ['type' => 'consumable', 'id' => 1, 'period' => 'fy'])] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsStringIgnoringCase('Snipe-IT', $html);
    }

    public function test_stock_card_period_kind_filter_and_selected_entry(): void
    {
        $card = $this->card('consumable', 1, ['period' => 'custom', 'from' => '2026-07-10', 'to' => '2026-07-31', 'kind' => 'issue'])->getData()['card'];
        $this->assertSame([36, 0, 20, 16], [$card['bf'], $card['in'], $card['out'], $card['closing']]);
        $this->assertSame(['GI-2026-0102'], collect($card['entries']->items())->pluck('document_number')->all());
        $this->assertTrue($card['filtered']);

        $card = $this->card('consumable', 1, ['kind' => 'in'])->getData()['card'];
        $this->assertTrue($card['filtered']);
        $this->assertSame(['GR-2026-0031'], collect($card['entries']->items())->pluck('document_number')->all());
        $this->assertSame([12, 24, 22, 14], [$card['bf'], $card['in'], $card['out'], $card['closing']], 'Totals cover the whole period.');

        $issueId = DB::table('gov_inventory_movements')->where('quantity', 20)->value('id');
        $view = $this->card('consumable', 1, ['entry' => $issueId]);
        $this->assertSame('GI-2026-0102', $view->getData()['card']['selected']['document_number']);
        $this->assertStringContainsString('Drafted and posted by different people', $view->render());

        $card = $this->card('consumable', 1, ['period' => 'custom', 'from' => '2026-10-01', 'to' => 'not-a-date'])->getData()['card'];
        $this->assertSame('fy', $card['period']->key, 'An invalid custom range falls back to the financial year.');
    }

    public function test_stock_card_reports_a_broken_chain_and_hides_correction_for_viewers(): void
    {
        $this->ledger();
        DB::table('gov_inventory_movements')->where('quantity', 20)->update(['balance_after' => 99]);
        $this->abilities(false);
        $view = $this->card('consumable', 1);
        $this->assertFalse($view->getData()['card']['chain']['ok']);
        $html = $view->render();
        $this->assertStringContainsString('Ledger chain broken', $html);
        $this->assertStringContainsString(e(__('storeops::storeops.stock_card.view_only_note')), $html);
        $this->assertStringNotContainsString('Correct with an adjustment', $html);
        $this->assertStringContainsString('You: view only', $html);
    }

    public function test_printable_card_carries_reconciliation_fingerprint_and_signatures(): void
    {
        $request = $this->req('/gov-store/operations/kardex/consumable/1/print');
        app()->instance('request', $request);
        $view = $this->controller()->printCard($request, 'consumable', 1);
        $html = $view->render();
        $fingerprint = $view->getData()['card']['fingerprint'];

        $this->assertMatchesRegularExpression('/^SC-[0-9A-F]{4}-[0-9A-F]{4}$/', $fingerprint);
        foreach (['Ministry of Test', 'Central Store', 'Secretariat, Dhaka', 'Card SC-CON-0001 · FY 2026–27', 'স্টক কার্ড',
            '12 brought forward + 24 received − 22 issued = 14 closing balance.', 'Ledger chain verified for all 4 entries',
            'Prepared by', 'Checked by', 'Approved by', 'Head of office', $fingerprint, 'Printed by Kamal Hossain (Storekeeper)'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('<x-gs', $html);
    }

    public function test_stock_card_export_lists_period_with_brought_forward_and_totals(): void
    {
        $this->ledger();
        $response = $this->controller()->exportCard($this->req('/gov-store/operations/kardex/consumable/1/export'), 'consumable', 1);
        ob_start(); $response->sendContent(); $csv = ob_get_clean();
        $lines = array_map('str_getcsv', array_filter(explode("\n", ltrim($csv, "\xEF\xBB\xBF"))));

        $this->assertSame('Balance brought forward', $lines[1][2]);
        $this->assertSame('12', $lines[1][6]);
        $this->assertSame(['GR-2026-0031', 'GI-2026-0102', 'ADJ-2026-0007'], [$lines[2][1], $lines[3][1], $lines[4][1]]);
        $this->assertSame(['24', '22', '14'], [$lines[5][4], $lines[5][5], $lines[5][6]]);
    }

    public function test_asset_card_lists_units_ready_list_and_tagged_ledger(): void
    {
        $view = $this->card('assetmodel', 5);
        $this->assertSame('storeops::register.asset-card', $view->name());
        $data = $view->getData();
        $this->assertSame([0, 3, 1, 2], [$data['card']['bf'], $data['card']['in'], $data['card']['out'], $data['card']['closing']]);
        $this->assertSame(['AST-0412', 'AST-0413', 'AST-0414'], $data['units']->pluck('tag')->all());
        $this->assertSame(['AST-0412'], $data['units']->where('ready', true)->pluck('tag')->values()->all());
        $this->assertSame(['AST-0412', 'AST-0413', 'AST-0414'], collect($data['card']['entries']->items())->first()['tags']);

        $html = $view->render();
        $this->assertStringNotContainsString('<x-gs', $html);
        foreach (['Units by asset tag', 'AST-0412, AST-0413, AST-0414', 'Kamal Hossain', 'Out for repair', 'Sep 2029',
            '1 ready to issue', route('hardware.show', 101), 'Print asset card', 'Manufacturer <strong>Dell</strong>'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        foreach (['GR-OTHER-0001', 'Projection', 'Snipe-IT'] as $absent) {
            $this->assertStringNotContainsStringIgnoringCase($absent, $html);
        }

        $issued = $this->card('assetmodel', 5, ['unit_status' => 'issued'])->render();
        $this->assertStringContainsString('Showing 1 of 3 units', $issued);
    }

    public function test_cards_render_in_bangla(): void
    {
        app()->setLocale('bn-BD');
        $html = $this->card('consumable', 1)->render();
        $this->assertStringContainsString('পূর্বের জের', $html);
        $this->assertStringContainsString('লেজার শৃঙ্খল যাচাইকৃত', $html);
        $html = $this->card('assetmodel', 5)->render();
        $this->assertStringContainsString('অ্যাসেট ট্যাগ অনুযায়ী একক', $html);
        $html = $this->controller()->index($this->req('/gov-store/operations/register'))->render();
        $this->assertStringContainsString('মনোযোগ প্রয়োজন', $html);
    }

    public function test_legacy_goods_issue_rows_keep_their_own_chain_and_number(): void
    {
        $this->ledger();
        // Posted before key canonicalisation: class-name key, legacy header, its own running balance.
        DB::table('gov_goods_issues')->insert(['id' => 9, 'issue_no' => 'GI-LEGACY-0009']);
        DB::table('gov_inventory_movements')->insert(['id' => '01a10751-a057-7352-baf4-7820dfe865d9', 'stockable_type' => \App\Models\Consumable::class,
            'stockable_id' => 1, 'movement_type' => 'OUT', 'quantity' => 3, 'balance_after' => -3,
            'document_type' => \GovStore\StoreOperations\Models\GoodsIssue::class, 'document_id' => '9',
            'company_id' => 20, 'location_id' => 10, 'created_by' => 1, 'created_at' => '2026-09-10 10:00:00']);

        $card = $this->card('consumable', 1)->getData()['card'];
        $this->assertTrue($card['chain']['ok']);
        $this->assertSame(11, $card['closing']);
        $legacy = collect($card['entries']->items())->last();
        $this->assertSame(['GI-LEGACY-0009', null, 'issue', 11], [$legacy['document_number'], $legacy['document_type'], $legacy['kind'], $legacy['balance']]);

        $paper = collect($this->controller()->index($this->req('/gov-store/operations/register'))->getData()['items']->items())->firstWhere('name', 'Paper');
        $this->assertSame([11, 'GI-LEGACY-0009', 'issue'], [$paper['balance'], $paper['last_document'], $paper['last_kind']]);
        $this->assertStringContainsString('GI-LEGACY-0009', $this->card('consumable', 1)->render());
    }

    public function test_ajax_table_other_office_items_and_unknown_types(): void
    {
        $request = Request::create('/gov-store/operations/kardex/consumable/1', 'GET', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        $view = $this->controller()->kardex($request, 'consumable', 1);
        $this->assertSame('storeops::register.kardex-table', $view->name());
        $this->assertCount(4, $view->getData()['movements']);

        foreach ([['consumable', 2], ['asset', 5]] as [$type, $id]) {
            try { $this->card($type, $id); $this->fail("$type $id must 404 for office 10."); }
            catch (HttpExceptionInterface $e) { $this->assertSame(404, $e->getStatusCode()); }
        }
    }
}
