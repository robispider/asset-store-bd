<?php

/**
 * Opt-in MySQL locking exercise for asset tagging. Creates a schema-only, uniquely named database, applies the
 * asset tagging migrations to it, and always drops only that database. Run: php asset-tagging-mysql-concurrency.php run
 */
require dirname(__DIR__, 3).'/vendor/autoload.php';
$root = dirname(__DIR__, 3);
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use GovStore\AssetTagging\Services\OfficeTagFacts;
use GovStore\Classification\Services\ProductTypeFinanceDefault;
use GovStore\Metadata\Services\MetadataValueStore;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\CustomFieldProvisioner;
use GovStore\StoreOperations\Services\LedgerPostingService;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

$source = config('database.connections.mysql.database');
$database = $argv[2] ?? ('govstore_tagging_test_'.date('YmdHis').'_'.bin2hex(random_bytes(4)));
if (! preg_match('/^govstore_tagging_test_[0-9]{14}_[a-f0-9]{8}$/D', $database) || $database === $source) throw new RuntimeException('Unsafe test database.');
$configuration = config('database.connections.mysql');
$pdo = new PDO('mysql:host='.$configuration['host'].';port='.$configuration['port'].';dbname='.$source,
    $configuration['username'], $configuration['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

const MIGRATIONS = [
    'packages/gov-store/metadata/src/database/migrations/2026_10_16_000001_create_gov_metadata_values_table.php',
    'packages/gov-store/classification/src/database/migrations/2026_10_16_000001_create_gov_finance_items_table.php',
    'packages/gov-store/store-operations/src/Database/migrations/2026_10_16_000003_add_tag_evidence_to_gov_asset_registrations.php',
    'packages/gov-store/asset-tagging/src/database/migrations/2026_10_16_000004_create_gov_asset_tag_sequences_table.php',
];

function context(): void {
    $context = app(TenantContext::class);
    $context->isActive = true; $context->isGlobal = false; $context->companyId = 20; $context->locationId = 10;
    $context->allowedCompanyIds = [20]; $context->allowedLocationIds = [10];
    $context->allowedInventoryCompanyIds = [20]; $context->allowedInventoryLocationIds = [10];
    auth()->setUser(App\Models\User::withoutGlobalScopes()->findOrFail(1));
    app()->instance(CustomFieldProvisioner::class, new class extends CustomFieldProvisioner { public function __construct() {} public function getDbColumn(string $identifier): ?string { return null; } });
    Event::forget(InventoryMovementCreated::class);
}
function switchDatabase(string $name): void {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name, 'cache.default' => 'array', 'asset-tagging.single_issuer' => true]);
    DB::purge('mysql');
    App\Models\Setting::$_cache = null;
    if (DB::connection()->getDatabaseName() !== $name) throw new RuntimeException('Test database mismatch.');
}
function receipt(string $number, int $model, int $quantity, string $date, int $status = 1): Document {
    context();
    $doc = Document::create(['document_number' => $number, 'type' => 'receipt', 'status' => 'DRAFT', 'purchase_type' => 'Purchase', 'supplier_id' => 7,
        'company_id' => 20, 'location_id' => 10, 'created_by' => 1, 'drafted_by' => 1, 'managed_by' => 1,
        'compiled_profile_snapshot' => ['items' => ['assetmodel_'.$model => ['create_assets' => ['enforced' => true, 'config' => ['status_id' => $status]],
            'issue_govt_tags' => ['enforced' => true, 'config' => []]]]]]);
    $doc->items()->create(['product_type' => 'assetmodel', 'product_id' => $model, 'quantity' => $quantity, 'unit_cost' => 10]);
    $doc->references()->create(['reference_type' => 'Supplier Challan', 'reference_number' => 'CH-'.$number, 'reference_date' => $date]);
    return $doc;
}
function together(array $documents, string $database): array {
    $processes = [];
    foreach ($documents as $doc) {
        $pipes = [];
        $processes[] = [proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', __FILE__, 'worker', $database, $doc->id],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes), $pipes];
    }
    $results = [];
    foreach ($processes as [$process, $pipes]) {
        fclose($pipes[0]); $results[] = trim(stream_get_contents($pipes[1]));
        $error = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Worker failed: '.$error);
    }
    return $results;
}
function ensure(bool $condition, string $message): void { if (! $condition) throw new RuntimeException($message); }

if (($argv[1] ?? '') === 'worker') {
    switchDatabase($database);
    $document = Document::withoutGlobalScopes()->findOrFail($argv[3]);
    context();
    // Hold the counter locks a little so the two postings genuinely overlap.
    App\Models\Asset::creating(function () { usleep(50000); });
    try { app(PostingPipelineManager::class)->materialize($document, 1); echo 'posted'; }
    catch (Throwable $error) { echo 'failed: '.$error->getMessage(); }
    exit;
}

if (($argv[1] ?? '') !== 'run') throw new RuntimeException('Run explicitly with argument run.');
$created = false;
try {
    $ddl = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) $ddl[] = $pdo->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM)[1];
    $pdo->exec('CREATE DATABASE `'.$database.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created = true;
    $pdo->exec('USE `'.$database.'`'); $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($ddl as $statement) $pdo->exec($statement);
    $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
    switchDatabase($database);
    foreach (MIGRATIONS as $migration) (require $root.'/'.$migration)->up();
    ensure(Schema::hasTable('gov_asset_tag_sequences') && Schema::hasColumn('gov_asset_registrations', 'tag_snapshot'), 'Migrations did not apply.');

    DB::table('companies')->insert(['id' => 20, 'name' => 'Isolated test company']);
    DB::table('locations')->insert(['id' => 10, 'name' => 'Isolated office 10', 'company_id' => 20]);
    DB::table('categories')->insert([['id' => 1, 'name' => 'Isolated laptops', 'category_type' => 'asset'], ['id' => 2, 'name' => 'Isolated chairs', 'category_type' => 'asset']]);
    DB::table('models')->insert([['id' => 1, 'name' => 'Isolated laptop', 'category_id' => 1], ['id' => 2, 'name' => 'Isolated chair', 'category_id' => 2]]);
    DB::table('status_labels')->insert(['id' => 1, 'name' => 'Ready', 'deployable' => 1, 'pending' => 0, 'archived' => 0]);
    DB::table('suppliers')->insert(['id' => 7, 'name' => 'Isolated supplier']);
    DB::table('users')->insert(['id' => 1, 'first_name' => 'Isolated', 'last_name' => 'Tester', 'username' => 'isolated-tagging', 'password' => 'unused', 'permissions' => '{"superuser":1}', 'activated' => 1, 'company_id' => 20, 'location_id' => 10]);
    DB::table('settings')->insert(['id' => 1, 'brand' => 1, 'auto_increment_assets' => 1, 'auto_increment_prefix' => 'GS-', 'zerofill_count' => 6, 'next_auto_tag_base' => 1, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('gov_finance_items')->insert(['gov_code' => '090809', 'group_name' => 'ICT', 'subgroup_name' => 'Laptops', 'item_name' => 'Laptop', 'status' => 'active']);
    context();
    $opening = Document::create(['document_number' => 'OB-10', 'type' => 'opening', 'status' => 'POSTED', 'company_id' => 20, 'location_id' => 10, 'created_by' => 1]);
    DB::table('gov_store_ledger_openings')->insert(['location_id' => 10, 'document_id' => $opening->id, 'opened_at' => now(), 'opened_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $store = app(MetadataValueStore::class);
    $store->put('company', 20, OfficeTagFacts::CODELISTS, ['locations' => [['code' => '01', 'name' => 'Head office']], 'divisions' => [['code' => '09', 'name' => 'ICT']]], null, 1);
    $store->put('office', 10, OfficeTagFacts::OFFICE, ['location_code' => '01', 'division_codes' => ['09'], 'default_division_code' => '09'], null, 1);
    $store->put('office', 10, OfficeTagFacts::ENABLEMENT, ['enabled' => true, 'reason' => 'Isolated concurrency fixture'], null, 1);
    $store->put('category', 1, ProductTypeFinanceDefault::NAMESPACE, ['gov_code' => '090809'], null, 1);

    // Same prefix, concurrently: one counter row, no duplicate and no gap.
    $results = together([receipt('GRN-SAME-A', 1, 3, '2026-10-01'), receipt('GRN-SAME-B', 1, 3, '2026-10-15')], $database);
    ensure($results === ['posted', 'posted'], 'Same-prefix postings failed: '.implode(' | ', $results));
    $tags = DB::table('gov_asset_registrations')->where('format_version', 'fd18-v1')->orderBy('asset_tag')->pluck('asset_tag')->all();
    ensure($tags === array_map(fn ($n) => '09080910260109'.str_pad((string) $n, 4, '0', STR_PAD_LEFT), range(1, 6)), 'Same-prefix sequence: '.implode(',', $tags));

    // Interleaved prefixes and mixed Govt./standard lines: counters are locked in sorted order, standard tags never repeat.
    $results = together([receipt('GRN-MIX-A', 1, 2, '2026-11-02'), receipt('GRN-MIX-B', 2, 2, '2026-11-03'),
        receipt('GRN-MIX-C', 1, 2, '2026-12-01')], $database);
    ensure($results === ['posted', 'posted', 'posted'], 'Interleaved postings failed: '.implode(' | ', $results));
    ensure(DB::table('gov_asset_registrations')->count() === 12, 'Unexpected registration count.');
    ensure(DB::table('gov_asset_registrations')->distinct()->count('asset_tag') === 12, 'Duplicate tag issued.');
    // Snipe-IT's own AssetObserver also advances the native base when any asset is created, so standard numbers may skip; they never repeat.
    $standard = DB::table('gov_asset_registrations')->where('format_version', 'native-v1')->pluck('asset_tag')->all();
    ensure(count($standard) === 2 && count(array_unique($standard)) === 2 && collect($standard)->every(fn ($tag) => preg_match('/^GS-\d{6}$/', $tag)),
        'Standard tags: '.implode(',', $standard));
    ensure((int) DB::table('settings')->value('next_auto_tag_base') > (int) max(array_map(fn ($tag) => (int) substr($tag, 3), $standard)), 'Native base behind an issued standard tag.');
    ensure((int) DB::table('gov_asset_tag_sequences')->where('scope_key', '09080911260109')->value('last_value') === 2, 'November counter wrong.');

    // A posting that fails after its counters are reserved (no valid asset status) rolls everything back.
    $results = together([receipt('GRN-ROLLBACK', 1, 2, '2026-10-20', 999)], $database);
    ensure(str_starts_with($results[0], 'failed'), 'An invalid asset status must stop posting.');
    ensure((int) DB::table('gov_asset_tag_sequences')->where('scope_key', '09080910260109')->value('last_value') === 6, 'Rollback moved the counter.');
    ensure(DB::table('gov_asset_registrations')->count() === 12, 'Rollback left registrations.');

    // A retired Finance code is ignored (default needs review): the line falls back to standard tags.
    DB::table('gov_finance_items')->where('gov_code', '090809')->update(['status' => 'retired']);
    $results = together([receipt('GRN-RETIRED', 1, 1, '2026-10-21')], $database);
    ensure($results === ['posted'], 'Retired-code receipt failed: '.$results[0]);
    ensure(DB::table('gov_asset_registrations')->orderByDesc('id')->value('format_version') === 'native-v1', 'Retired code must give a standard tag.');
    echo "PASS: same-prefix concurrency, interleaved prefixes, mixed standard tags, rollback and retired-code fallback in {$database}\n";
} finally {
    DB::disconnect('mysql');
    if ($created) { $pdo->exec('DROP DATABASE `'.$database.'`'); echo "Removed exact isolated database {$database}\n"; }
}
