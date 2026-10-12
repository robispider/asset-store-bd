<?php

/** Opt-in MySQL locking exercise. Creates a schema-only, uniquely named database and always drops only that database. */
require dirname(__DIR__, 3).'/vendor/autoload.php';
$root = dirname(__DIR__, 3);
$app = require $root.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\LedgerPostingService;
use GovStore\StoreOperations\Services\TransferPostingService;
use GovStore\StoreOperations\Events\InventoryMovementCreated;
use GovStore\TenantScope\Contexts\TenantContext;

$source = config('database.connections.mysql.database');
$database = $argv[2] ?? ('govstore_storeops_test_'.date('YmdHis').'_'.bin2hex(random_bytes(4)));
if (! preg_match('/^govstore_storeops_test_[0-9]{14}_[a-f0-9]{8}$/D', $database) || $database === $source) throw new RuntimeException('Unsafe test database.');
$configuration = config('database.connections.mysql');
$pdo = new PDO('mysql:host='.$configuration['host'].';port='.$configuration['port'].';dbname='.$source,
    $configuration['username'], $configuration['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

function context(int $office): void {
    $context = app(TenantContext::class);
    $context->isActive = true; $context->isGlobal = false; $context->companyId = 20; $context->locationId = $office;
    $context->allowedCompanyIds = [20]; $context->allowedLocationIds = [$office];
    $context->allowedInventoryCompanyIds = [20]; $context->allowedInventoryLocationIds = [$office];
    auth()->setUser(App\Models\User::withoutGlobalScopes()->findOrFail(1));
    config(['govstore-access.mode' => 'enforce']);
    Event::forget(InventoryMovementCreated::class);
    Event::listen(InventoryMovementCreated::class, [GovStore\StoreOperations\Listeners\UpdateSnipeQuantity::class, 'handle']);
}
function switchDatabase(string $name): void {
    config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name, 'cache.default' => 'array']);
    DB::purge('mysql');
    if (DB::connection()->getDatabaseName() !== $name) throw new RuntimeException('Test database mismatch.');
}
function transfer(int $office, int $target, int $quantity, string $number): Document {
    context($office);
    $doc = Document::create(['document_number' => $number, 'type' => 'transfer', 'status' => 'DRAFT',
        'company_id' => 20, 'location_id' => $office, 'created_by' => 1, 'drafted_by' => 1, 'managed_by' => 1,
        'destination_location_id' => $target, 'transfer_reason' => 'Isolated concurrency fixture']);
    $item = $doc->items()->create(['product_type' => 'consumable', 'product_id' => $office === 10 ? 1 : 2, 'quantity' => $quantity]);
    $item->metadata()->create(['field_key' => 'destination_stockable_id', 'value' => $target === 10 ? '1' : '2', 'row_index' => 0]);
    return $doc;
}
function together(array $documents, string $database): array {
    $processes = [];
    foreach ($documents as $doc) {
        $pipes = [];
        $processes[] = [proc_open([PHP_BINARY, '-d', 'xdebug.mode=off', __FILE__, 'worker', $database, $doc->id],
            [0 => ['pipe','r'], 1 => ['pipe','w'], 2 => ['pipe','w']], $pipes), $pipes];
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
    context((int) $document->location_id);
    Event::listen(InventoryMovementCreated::class, function ($event) { if ($event->movement->movement_type === 'OUT') usleep(200000); });
    try { app(TransferPostingService::class)->post($document, 1); echo 'posted'; }
    catch (Exception $error) {
        if (! str_contains($error->getMessage(), 'Insufficient stock')) throw $error;
        echo 'insufficient';
    }
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
    DB::table('companies')->insert(['id' => 20, 'name' => 'Isolated test company']);
    foreach ([10, 11] as $office) DB::table('locations')->insert(['id' => $office, 'name' => 'Isolated office '.$office, 'company_id' => 20]);
    DB::table('categories')->insert(['id' => 1, 'name' => 'Isolated supplies', 'category_type' => 'consumable']);
    DB::table('users')->insert(['id' => 1, 'first_name' => 'Isolated', 'last_name' => 'Tester', 'username' => 'isolated-storeops', 'password' => 'unused', 'permissions' => '{}', 'activated' => 1, 'company_id' => 20, 'location_id' => 10]);
    foreach ([10, 11] as $office) {
        DB::table('gov_office_memberships')->insert(['user_id' => 1, 'location_id' => $office, 'status' => 'active']);
        DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => $office, 'role_slug' => 'storekeeper']);
        DB::table('consumables')->insert(['id' => $office === 10 ? 1 : 2, 'name' => 'Isolated paper', 'category_id' => 1, 'qty' => 10, 'location_id' => $office, 'company_id' => 20]);
        context($office);
        $opening = Document::create(['document_number' => 'OB-'.$office, 'type' => 'opening', 'status' => 'POSTED', 'company_id' => 20, 'location_id' => $office, 'created_by' => 1]);
        app(LedgerPostingService::class)->postMovement('consumable', $office === 10 ? 1 : 2, 'IN', 10, $opening, 20, $office, 1);
        DB::table('gov_store_ledger_openings')->insert(['location_id' => $office, 'document_id' => $opening->id, 'opened_at' => now(), 'opened_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
    $results = together([transfer(10, 11, 3, 'TR-OPPOSITE-A'), transfer(11, 10, 3, 'TR-OPPOSITE-B')], $database);
    ensure($results === ['posted', 'posted'], 'Opposite-direction transfers failed: '.implode(' | ', $results));
    ensure(DB::table('consumables')->orderBy('id')->pluck('qty')->map(fn ($n) => (int) $n)->all() === [10,10], 'Opposite transfer projections disagree.');
    $documents = [transfer(10, 11, 7, 'TR-COMPETE-A'), transfer(10, 11, 7, 'TR-COMPETE-B')];
    $results = together($documents, $database); sort($results);
    ensure($results === ['insufficient', 'posted'], 'Concurrent overspend must post exactly once.');
    ensure(DB::table('consumables')->orderBy('id')->pluck('qty')->map(fn ($n) => (int) $n)->all() === [3,17], 'Concurrent projections disagree.');
    ensure(DB::table('gov_inventory_movements')->where('balance_after','<',0)->count() === 0, 'Negative ledger balance.');
    ensure(DB::table('gov_documents')->where('type','transfer')->where('status','POSTED')->count() === 3, 'Unexpected committed transfer count.');
    ensure(DB::table('gov_inventory_movements')->count() === 8, 'Rollback left extra movements.');
    echo "PASS: opposite transfers, concurrent overspend, native projection and rollback in {$database}\n";
} finally {
    DB::disconnect('mysql');
    if ($created) { $pdo->exec('DROP DATABASE `'.$database.'`'); echo "Removed exact isolated database {$database}\n"; }
}
