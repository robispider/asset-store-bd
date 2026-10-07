<?php

namespace Tests\Feature\GovStore;

use App\Models\Consumable;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Services\DocumentNumberService;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\StoreOperations\Services\LedgerPostingService;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use GovStore\StoreOperations\Services\ProfileCompilerService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;

/** Store Operations workflow tests use an isolated SQLite database only. */
class StoreOperationsWorkflowTest extends TestCase
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array']);
        DB::purge('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');

        Schema::create('consumables', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->string('item_no')->nullable();
            $table->integer('category_id')->nullable();
            $table->integer('qty')->default(0);
            $table->integer('location_id');
            $table->integer('company_id')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });
        Schema::create('gov_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_number')->unique();
            $table->string('type');
            $table->string('status')->default('DRAFT');
            $table->json('compiled_profile_snapshot')->nullable();
            $table->integer('company_id')->nullable();
            $table->integer('location_id')->nullable();
            $table->integer('created_by');
            $table->integer('drafted_by')->nullable();
            $table->integer('posted_by')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->integer('managed_by')->nullable();
            $table->uuid('source_document_id')->nullable();
            $table->string('adjustment_reason', 32)->nullable();
            $table->unsignedInteger('issued_to_user_id')->nullable();
            $table->string('issue_department', 150)->nullable();
            $table->string('purchase_type')->nullable();
            $table->timestamps();
        });
        Schema::create('gov_document_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->string('product_type');
            $table->unsignedInteger('product_id');
            $table->integer('quantity');
            $table->decimal('unit_cost', 15, 2)->nullable();
            $table->timestamps();
        });
        Schema::create('gov_document_item_meta', function (Blueprint $table) {
            $table->increments('id');
            $table->uuid('document_item_id');
            $table->string('field_key');
            $table->text('value');
            $table->integer('row_index')->default(0);
        });
        Schema::create('gov_document_timelines', function (Blueprint $table) {
            $table->increments('id');
            $table->string('document_type');
            $table->uuid('document_id');
            $table->string('state');
            $table->unsignedInteger('user_id');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('gov_document_references', function (Blueprint $table) {
            $table->increments('id');
            $table->string('document_type');
            $table->uuid('document_id');
            $table->string('reference_type');
            $table->string('reference_number');
            $table->date('reference_date')->nullable();
            $table->timestamps();
        });
        Schema::create('gov_inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('stockable_type');
            $table->unsignedInteger('stockable_id');
            $table->string('movement_type');
            $table->integer('quantity');
            $table->integer('balance_after')->nullable();
            $table->string('document_type');
            $table->uuid('document_id');
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
        Schema::create('gov_store_ledger_openings', function (Blueprint $table) {
            $table->unsignedInteger('location_id')->primary();
            $table->uuid('document_id')->unique();
            $table->dateTime('opened_at');
            $table->unsignedInteger('opened_by');
            $table->timestamps();
        });
        Schema::create('gov_store_document_sequences', function (Blueprint $table) {
            $table->string('prefix', 8);
            $table->unsignedSmallInteger('sequence_year');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->primary(['prefix', 'sequence_year']);
        });
        Schema::create('gov_goods_issues', function (Blueprint $table) {
            $table->increments('id');
            $table->string('issue_no')->unique();
        });

        $context = new TenantContext;
        $context->companyId = 20;
        $context->locationId = 10;
        $context->allowedLocationIds = [10];
        app()->instance(TenantContext::class, $context);
        app()->instance(ProfileCompilerService::class, Mockery::mock(ProfileCompilerService::class)
            ->shouldReceive('compileDocument')->zeroOrMoreTimes()->andReturn(['items' => []])->getMock());

        Event::fake([InventoryMovementCreated::class]);
        DB::table('consumables')->insert(['id' => 1, 'name' => 'Paper', 'qty' => 20, 'location_id' => 10, 'company_id' => 20]);
    }

    public function test_sequence_continues_after_legacy_goods_issue_numbers(): void
    {
        $prefix = 'GI-'.now()->format('Y').'-';
        DB::table('gov_goods_issues')->insert(['issue_no' => $prefix.'000009']);
        $numbers = app(DocumentNumberService::class);

        $this->assertSame($prefix.'000010', $numbers->generate('GI', 'gov_documents', 'document_number'));
        $this->assertSame($prefix.'000011', $numbers->generate('GI', 'gov_documents', 'document_number'));
    }

    public function test_ledger_requires_opening_canonicalizes_aliases_and_rejects_negative_stock(): void
    {
        $document = $this->document('issue', 'GI-TEST-000001');
        $ledger = app(LedgerPostingService::class);

        try {
            $ledger->postMovement('consumable', 1, 'OUT', 1, $document, 20, 10, 1);
            $this->fail('Posting must be gated until office opening stock is recorded.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Opening stock', $exception->getMessage());
        }

        $opening = $this->document('opening', 'OB-TEST-000001', 'POSTED');
        $ledger->postMovement(Consumable::class, 1, 'IN', 20, $opening, 20, 10, 1);
        DB::table('gov_store_ledger_openings')->insert([
            'location_id' => 10, 'document_id' => $opening->id, 'opened_at' => now(), 'opened_by' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $movement = $ledger->postMovement('App\\Models\\Consumable', 1, 'OUT', 5, $document, 20, 10, 1);
        $this->assertSame('consumable', $movement->stockable_type);
        $this->assertSame(15, (int) $movement->balance_after);
        $this->assertSame(2, InventoryMovement::withoutGlobalScopes()->count());

        try {
            $ledger->postMovement('consumable', 1, 'OUT', 16, $document, 20, 10, 1);
            $this->fail('Ledger posting must reject a negative resulting balance.');
        } catch (\Exception $exception) {
            $this->assertStringContainsString('Insufficient stock', $exception->getMessage());
        }
        $this->assertSame(2, InventoryMovement::withoutGlobalScopes()->count());
    }

    public function test_receipt_issue_and_adjustment_follow_one_office_ledger_chain(): void
    {
        $ledger = app(LedgerPostingService::class);
        $opening = $this->document('opening', 'OB-TEST-000001', 'POSTED');
        $ledger->postMovement('consumable', 1, 'IN', 20, $opening, 20, 10, 1);
        DB::table('gov_store_ledger_openings')->insert([
            'location_id' => 10, 'document_id' => $opening->id, 'opened_at' => now(), 'opened_by' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $receipts = app(GoodsReceiptService::class);
        $pipeline = app(PostingPipelineManager::class);
        $receipt = $receipts->saveDraft([], [['type' => 'consumable', 'id' => 1, 'qty' => 5]], 1);
        $pipeline->materialize($receipt, 1);

        $issue = $receipts->saveDraft(['issued_to_user_id' => 2], [['type' => 'consumable', 'id' => 1, 'qty' => 3]], 1, null, 'issue');
        $pipeline->materialize($issue, 1);

        $adjustment = $receipts->saveDraft([
            'adjustment_reason' => 'PHYSICAL_COUNT', 'source_document_id' => $receipt->id,
        ], [['type' => 'consumable', 'id' => 1, 'qty' => 1]], 1, null, 'adjustment');
        $line = $adjustment->items()->firstOrFail();
        $line->metadata()->create(['field_key' => 'adjustment_direction', 'value' => 'IN', 'row_index' => 0]);
        $pipeline->materialize($adjustment, 1);

        $this->assertSame('POSTED', $receipt->fresh()->status);
        $this->assertSame('POSTED', $issue->fresh()->status);
        $this->assertSame('POSTED', $adjustment->fresh()->status);
        $this->assertSame(2, (int) $issue->fresh()->issued_to_user_id);
        $this->assertSame([
            ['IN', 20], ['IN', 25], ['OUT', 22], ['IN', 23],
        ], InventoryMovement::withoutGlobalScopes()->orderBy('created_at')->orderBy('id')
            ->get(['movement_type', 'balance_after'])->map(fn ($row) => [$row->movement_type, (int) $row->balance_after])->all());
        $this->assertSame(23, (int) InventoryMovement::withoutGlobalScopes()->orderByDesc('created_at')->orderByDesc('id')->value('balance_after'));
    }

    private function document(string $type, string $number, string $status = 'DRAFT'): Document
    {
        return Document::withoutGlobalScopes()->create([
            'document_number' => $number, 'type' => $type, 'status' => $status,
            'compiled_profile_snapshot' => ['items' => []], 'company_id' => 20, 'location_id' => 10, 'created_by' => 1,
        ]);
    }
}
