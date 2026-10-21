<?php

namespace Tests\Feature\GovStore\Concerns;

use App\Models\Setting;
use App\Models\User;
use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\AssetTagging\Services\TagIssuanceService;
use GovStore\Classification\Models\FinanceItem;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\CustomFieldProvisioner;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Mockery;

/**
 * Isolated SQLite fixture for asset tagging (never the live DB): company 20 with offices 10 and 11,
 * two asset models, native tag settings, two held Finance items and a receipt builder.
 * Using classes declare OFFICE, OTHER_OFFICE and COMPANY constants.
 */
trait AssetTaggingFixtures
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'LOG_CHANNEL' => 'stderr', 'CACHE_DRIVER' => 'array', 'SESSION_DRIVER' => 'array'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require __DIR__.'/../../../../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array',
            'asset-tagging.single_issuer' => true, 'logging.default' => 'null']);
        DB::purge('sqlite');
        $this->schema();
        $this->seedReference();
        $context = new TenantContext;
        $context->companyId = static::COMPANY;
        $context->locationId = static::OFFICE;
        $context->allowedLocationIds = [static::OFFICE];
        $context->allowedInventoryLocationIds = [static::OFFICE];
        $context->allowedInventoryCompanyIds = [static::COMPANY];
        app()->instance(TenantContext::class, $context);
        app()->instance(CustomFieldProvisioner::class, Mockery::mock(CustomFieldProvisioner::class)->shouldReceive('getDbColumn')->andReturnNull()->getMock());
        app()->instance(\App\Observers\AssetObserver::class, Mockery::mock(\App\Observers\AssetObserver::class)->makePartial()
            ->shouldReceive('created')->andReturnNull()->getMock());
        app()->forgetInstance(TagIssuanceService::class);
        Event::fake([\GovStore\StoreOperations\Events\InventoryMovementCreated::class]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('isSuperUser')->andReturn(true);
        auth()->setUser($user);
    }

    protected function tearDown(): void
    {
        Setting::$_cache = null;
        parent::tearDown();
    }

    // ---------------------------------------------------------------- fixtures

    protected function lists(): array
    {
        return ['locations' => [['code' => '01', 'name' => 'Head Office'], ['code' => '02', 'name' => 'Branch']],
            'divisions' => [['code' => '01', 'name' => 'Administration'], ['code' => '09', 'name' => 'ICT']]];
    }

    protected function enableOffice(): void
    {
        $store = app(MetadataValueStore::class);
        $store->put('company', static::COMPANY, OfficeTagFacts::CODELISTS, $this->lists(), null, 1);
        $store->put('office', static::OFFICE, OfficeTagFacts::OFFICE, ['location_code' => '01', 'division_codes' => ['09'], 'default_division_code' => '09'], null, 1);
        $store->put('office', static::OFFICE, OfficeTagFacts::ENABLEMENT, ['enabled' => true, 'reason' => 'Validated pilot office'], null, 1);
    }

    protected function activate(string $code, ?string $unspsc = null): void
    {
        FinanceItem::where('gov_code', $code)->update(['status' => 'active', 'unspsc_code' => $unspsc]);
    }

    protected function receipt(array $lines, ?string $challanDate, string $number = 'GRN-TAG-1', string $purchaseType = 'Purchase'): Document
    {
        $receipt = Document::create(['document_number' => $number, 'type' => 'receipt', 'status' => 'DRAFT', 'purchase_type' => $purchaseType,
            'supplier_id' => $purchaseType === 'Purchase' ? 7 : null, 'company_id' => static::COMPANY, 'location_id' => static::OFFICE, 'created_by' => 1,
            'compiled_profile_snapshot' => ['items' => collect($lines)->mapWithKeys(fn ($line) => ['assetmodel_'.$line['model'] => [
                'create_assets' => ['enforced' => true, 'config' => ['status_id' => 1]],
                'issue_govt_tags' => ['enforced' => true, 'config' => []],
            ]])->all()]]);
        foreach ($lines as $line) {
            $receipt->items()->create(['product_type' => 'assetmodel', 'product_id' => $line['model'], 'quantity' => $line['qty'], 'unit_cost' => 10]);
        }
        $receipt->references()->create(['reference_type' => 'Supplier Challan', 'reference_number' => 'CH-1', 'reference_date' => $challanDate]);

        return $receipt->fresh();
    }

    protected function seedReference(): void
    {
        DB::table('companies')->insert(['id' => static::COMPANY, 'name' => 'Ministry of Test']);
        DB::table('locations')->insert([['id' => static::OFFICE, 'name' => 'Office A', 'company_id' => static::COMPANY],
            ['id' => static::OTHER_OFFICE, 'name' => 'Office B', 'company_id' => static::COMPANY]]);
        DB::table('users')->insert(['id' => 1, 'first_name' => 'Store keeper']);
        DB::table('categories')->insert([['id' => 1, 'name' => 'Laptops', 'category_type' => 'asset'], ['id' => 2, 'name' => 'Chairs', 'category_type' => 'asset']]);
        DB::table('models')->insert([['id' => 1, 'name' => 'Laptop', 'category_id' => 1], ['id' => 2, 'name' => 'Office chair', 'category_id' => 2]]);
        DB::table('status_labels')->insert(['id' => 1, 'name' => 'Ready', 'deployable' => 1, 'archived' => 0, 'pending' => 0]);
        DB::table('suppliers')->insert(['id' => 7, 'name' => 'Supplier Ltd.']);
        DB::table('settings')->insert(['id' => 1, 'brand' => 1, 'auto_increment_assets' => 1, 'auto_increment_prefix' => 'GS-', 'zerofill_count' => 6,
            'next_auto_tag_base' => 100, 'full_multiple_companies_support' => 0]);
        Setting::$_cache = null;
        DB::table('gov_store_ledger_openings')->insert(['location_id' => static::OFFICE, 'document_id' => 'opening', 'opened_at' => now(), 'opened_by' => 1,
            'created_at' => now(), 'updated_at' => now()]);
        DB::table('gov_finance_items')->insert([
            ['gov_code' => '090809', 'group_name' => 'ICT', 'subgroup_name' => 'Laptops', 'item_name' => 'Laptop', 'status' => 'held'],
            ['gov_code' => '090110', 'group_name' => 'ICT', 'subgroup_name' => 'Computers', 'item_name' => 'Desktop', 'status' => 'held'],
        ]);
    }

    protected function schema(): void
    {
        $simple = ['companies' => ['name'], 'suppliers' => ['name'], 'manufacturers' => ['name']];
        foreach ($simple as $name => $columns) {
            Schema::create($name, function (Blueprint $t) use ($columns) {
                $t->increments('id');
                foreach ($columns as $column) {
                    $t->string($column)->nullable();
                }
                $t->timestamp('deleted_at')->nullable();
                $t->timestamps();
            });
        }
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('first_name')->nullable(); $t->string('last_name')->nullable(); $t->integer('location_id')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('locations', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('company_id')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('categories', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->string('category_type')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('models', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('category_id'); $t->integer('manufacturer_id')->nullable(); $t->integer('company_id')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps(); });
        Schema::create('status_labels', function (Blueprint $t) { $t->increments('id'); $t->string('name')->nullable(); $t->boolean('deployable'); $t->boolean('archived'); $t->boolean('pending')->default(0); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('settings', function (Blueprint $t) {
            $t->increments('id'); $t->integer('brand')->default(1); $t->boolean('auto_increment_assets')->default(0); $t->string('auto_increment_prefix')->nullable();
            $t->integer('zerofill_count')->default(5); $t->bigInteger('next_auto_tag_base')->default(1); $t->string('full_multiple_companies_support')->default('0'); $t->timestamps();
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->increments('id'); $t->string('name')->nullable(); $t->integer('model_id'); $t->integer('status_id'); $t->integer('location_id')->nullable(); $t->integer('rtd_location_id')->nullable();
            $t->integer('company_id')->nullable(); $t->integer('assigned_to')->nullable(); $t->string('assigned_type')->nullable(); $t->string('asset_tag'); $t->string('serial')->nullable();
            $t->integer('supplier_id')->nullable(); $t->decimal('purchase_cost')->nullable(); $t->date('purchase_date')->nullable(); $t->integer('created_by')->nullable();
            $t->integer('warranty_months')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('gov_documents', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('document_number')->unique(); $t->string('type'); $t->string('status')->default('DRAFT');
            $t->json('compiled_profile_snapshot')->nullable(); $t->integer('company_id')->nullable(); $t->integer('location_id')->nullable();
            $t->integer('created_by'); $t->integer('drafted_by')->nullable(); $t->integer('posted_by')->nullable(); $t->timestamp('posted_at')->nullable();
            $t->integer('managed_by')->nullable(); $t->uuid('source_document_id')->nullable(); $t->string('adjustment_reason', 32)->nullable();
            $t->unsignedInteger('issued_to_user_id')->nullable(); $t->string('issue_department', 150)->nullable(); $t->string('purchase_type')->nullable();
            $t->unsignedInteger('supplier_id')->nullable(); $t->unsignedInteger('destination_location_id')->nullable(); $t->string('transfer_reason', 500)->nullable(); $t->timestamps();
        });
        Schema::create('gov_document_items', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->uuid('document_id'); $t->string('product_type'); $t->unsignedInteger('product_id'); $t->integer('quantity'); $t->decimal('unit_cost', 15, 2)->nullable(); $t->timestamps();
        });
        Schema::create('gov_document_item_meta', function (Blueprint $t) { $t->increments('id'); $t->uuid('document_item_id'); $t->string('field_key'); $t->text('value'); $t->integer('row_index')->default(0); });
        Schema::create('gov_document_timelines', function (Blueprint $t) { $t->increments('id'); $t->string('document_type'); $t->uuid('document_id'); $t->string('state'); $t->unsignedInteger('user_id'); $t->text('notes')->nullable(); $t->timestamp('created_at')->useCurrent(); });
        Schema::create('gov_document_references', function (Blueprint $t) { $t->increments('id'); $t->string('document_type'); $t->uuid('document_id'); $t->string('reference_type'); $t->string('reference_number'); $t->date('reference_date')->nullable(); $t->timestamps(); });
        Schema::create('gov_inventory_movements', function (Blueprint $t) {
            $t->uuid('id')->primary(); $t->string('stockable_type'); $t->unsignedInteger('stockable_id'); $t->string('movement_type'); $t->integer('quantity'); $t->integer('balance_after')->nullable();
            $t->string('document_type'); $t->uuid('document_id'); $t->unsignedInteger('company_id')->nullable(); $t->unsignedInteger('location_id')->nullable(); $t->unsignedInteger('created_by'); $t->text('notes')->nullable(); $t->timestamp('created_at')->useCurrent();
        });
        Schema::create('gov_store_ledger_openings', function (Blueprint $t) { $t->unsignedInteger('location_id')->primary(); $t->string('document_id')->unique(); $t->dateTime('opened_at'); $t->unsignedInteger('opened_by'); $t->timestamps(); });
        Schema::create('gov_catalog_snipe_mappings', function (Blueprint $t) { $t->id(); $t->string('code', 50)->unique(); $t->unsignedInteger('category_id')->index(); $t->timestamps(); });
        Schema::create('gov_catalog_nodes', function (Blueprint $t) { $t->id(); $t->string('code', 50)->unique(); });
        Schema::create('gov_category_governance', function (Blueprint $t) { $t->id(); $t->unsignedInteger('category_id'); $t->string('governance_type'); $t->unsignedInteger('created_by_company_id')->nullable(); $t->unsignedInteger('created_by_user_id')->nullable(); $t->timestamps(); });
        Schema::create('gov_experiment_records', function (Blueprint $t) { $t->id(); $t->string('run_id'); $t->string('table_name'); $t->string('record_key'); $t->timestamps(); });
        Schema::create('gov_tenant_scope_mappings', function (Blueprint $t) { $t->increments('id'); $t->string('reference_type'); $t->integer('reference_id'); $t->string('scope_type'); $t->integer('scope_id'); $t->boolean('is_active')->default(1); });
        foreach ([
            'packages/gov-store/store-operations/src/Database/migrations/2024_03_05_000000_create_gov_asset_registrations_table.php',
            'packages/gov-store/metadata/src/database/migrations/2026_10_16_000001_create_gov_metadata_values_table.php',
            'packages/gov-store/classification/src/database/migrations/2026_10_16_000001_create_gov_finance_items_table.php',
            'packages/gov-store/classification/src/database/migrations/2026_10_16_000002_add_unique_category_to_catalog_snipe_mappings.php',
            'packages/gov-store/store-operations/src/Database/migrations/2026_10_16_000003_add_tag_evidence_to_gov_asset_registrations.php',
            'packages/gov-store/asset-tagging/src/database/migrations/2026_10_16_000004_create_gov_asset_tag_sequences_table.php',
        ] as $migration) {
            (require base_path($migration))->up();
        }
    }
}
