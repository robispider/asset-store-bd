<?php

namespace Tests\Experimentation;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Component;
use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use GovStore\CustomRequests\Factories\RequestableFactory;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Services\CatalogService;
use GovStore\Experimentation\Services\ActorContext;
use GovStore\Experimentation\Services\BangladeshPeople;
use GovStore\Experimentation\Services\BangladeshScenario;
use GovStore\Experimentation\Services\ExperimentManager;
use GovStore\Experimentation\Services\ExperimentLedgerOpening;
use GovStore\Experimentation\Services\ExperimentWiper;
use GovStore\Experimentation\Services\RecordRegistry;
use GovStore\OfficeMembership\Models\OfficeMembership;
use GovStore\OfficeMembership\Models\OfficeResponsibility;
use GovStore\StoreOperations\Enums\DocumentState;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExperimentTest extends TestCase
{
    public function createApplication()
    {
        $root = dirname(__DIR__, 4);
        $database = trim(file_get_contents($root.'/storage/framework/experiment-test-database.txt'));
        if (! preg_match('/^govstore_experiment_test_[0-9]{14}$/', $database)) {
            throw new \RuntimeException('Prepare a separate experiment test database first.');
        }
        foreach (['APP_ENV' => 'testing', 'DB_DATABASE' => $database, 'CACHE_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'BCRYPT_ROUNDS' => '4'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
        if (DB::connection()->getDatabaseName() !== $database) {
            throw new \RuntimeException('Refusing to test outside the separate database.');
        }

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['govstore-experiments.enabled' => true, 'govstore-experiments.database_reset_enabled' => true, 'govstore-experiments.connection' => 'sync', 'app.debug' => false]);
        config(['logging.default' => 'experiment_test', 'logging.channels.experiment_test' => ['driver' => 'single', 'path' => storage_path('framework/experiment-test.log')]]);
        if (! Schema::hasTable('gov_experiment_runs')) {
            (require dirname(__DIR__).'/src/database/migrations/2026_10_04_000001_create_gov_experiment_tables.php')->up();
        }
        if (! Setting::first()) {
            Setting::factory()->create(['site_name' => 'Experiment Tests', 'locale' => 'en-US', 'full_multiple_companies_support' => 1]);
        }
    }

    public function test_names_are_deterministic_bangladesh_style_and_never_real_contact_addresses(): void
    {
        $names = app(BangladeshPeople::class);
        $this->assertSame($names->name(3, 2026), $names->name(3, 2026));
        $this->assertMatchesRegularExpression('/[\x{0980}-\x{09FF}]/u', $names->name(3)['display_name']);
        $this->assertSame('mdkamrulislam123', $names->username(['first_name' => 'Md. Kamrul', 'last_name' => 'Islam'], 123));
    }

    public function test_only_real_superusers_can_manage_and_production_is_blocked(): void
    {
        $user = User::factory()->create(['permissions' => json_encode(['admin' => '1'])]);
        $this->actingAs($user)->get(route('gov.experiments.index'))->assertForbidden();
        $root = $this->root();
        $this->actingAs($root)->get(route('gov.experiments.index'))->assertOk();
        $this->app->instance('env', 'production');
        $this->actingAs($root)->get(route('gov.experiments.index'))->assertForbidden();
        $this->app->instance('env', 'testing');
        config(['govstore-experiments.enabled' => false]);
        $this->actingAs($root)->get(route('gov.experiments.index'))->assertForbidden();
    }

    public function test_populate_quick_verify_rerun_preview_and_wipe_preserve_unrelated_data(): void
    {
        Storage::fake('local');
        $root = $this->root();
        $outside = Company::factory()->create(['name' => 'Outside experiment '.Str::uuid()]);
        $this->assertNotNull($outside->id);
        $manager = app(ExperimentManager::class);
        $run = $manager->populate($root, 'quick');
        $manager->dispatch($run, $root);
        $run->refresh();
        $this->assertSame('ready', $run->status, $run->error ?? json_encode($run->report['verification'] ?? []));
        $this->assertSame('1234567890', $run->password);
        $this->assertSame(64, count(app(RecordRegistry::class)->ids($run, 'users')));
        $this->assertSame(120, count(app(RecordRegistry::class)->ids($run, 'assets')));
        $officeIds = app(RecordRegistry::class)->ids($run, 'locations');
        $openings = DB::table('gov_store_ledger_openings')->whereIn('location_id', $officeIds)->get();
        $this->assertCount(4, $openings);
        $this->assertTrue($run->report['verification']['checks']['ledger_openings']);
        $this->assertSame(4, app(RecordRegistry::class)->counts($run)['gov_store_ledger_openings']);
        foreach ($openings as $opening) {
            $document = Document::withoutGlobalScopes()->findOrFail($opening->document_id);
            $this->assertSame('opening', $document->type);
            $this->assertSame('POSTED', $document->status);
            $this->assertEquals($opening->opened_by, $document->created_by);
            $this->assertEquals($opening->opened_by, $document->drafted_by);
            $this->assertEquals($opening->opened_by, $document->posted_by);
            $this->assertTrue(DB::table('gov_office_responsibilities')->where('location_id', $opening->location_id)
                ->where('user_id', $opening->opened_by)->where('role_slug', 'storekeeper')->exists());
            $movements = DB::table('gov_inventory_movements')->where('document_id', $document->id)->get();
            $this->assertCount(4, $movements);
            foreach ($movements as $movement) {
                $this->assertSame('component', $movement->stockable_type);
                $this->assertEquals(1, $movement->quantity);
                $this->assertEquals(1, $movement->balance_after);
                $this->assertEquals(101, DB::table('components')->where('id', $movement->stockable_id)->value('qty'));
            }
        }
        $employee = User::withoutGlobalScopes()->whereIn('id', app(RecordRegistry::class)->ids($run, 'users'))->whereNotNull('location_id')->firstOrFail();
        $this->assertSame(app(BangladeshPeople::class)->username($employee->getAttributes(), $employee->id), $employee->username);
        $this->assertTrue(Hash::check($run->password, $employee->password));
        $this->assertTrue(Auth::guard()->validate(['username' => $employee->username, 'password' => $run->password, 'activated' => 1]));
        app(ActorContext::class)->run($employee, Location::withoutGlobalScopes()->findOrFail($employee->location_id), function () use ($run, $employee) {
            $catalog = app(CatalogService::class)->paginate([], 100)->getCollection();
            $this->assertSame(12, $catalog->where('type', 'consumable')->count());
            $this->assertSame(8, $catalog->where('type', 'accessory')->count());
            $this->assertSame(0, $catalog->where('type', 'component')->count());
            $this->assertTrue($catalog->contains(fn ($item) => str_contains($item->name, 'Office Chairs')));
            $this->assertTrue($catalog->contains(fn ($item) => str_contains($item->name, 'Desktop Computers')));
            $this->assertFalse(Asset::whereIn('id', app(RecordRegistry::class)->ids($run, 'assets'))->where('location_id', '!=', $employee->location_id)->exists());
        });
        $this->actingAs($root)->get(route('gov.experiments.show', $run))->assertOk()
            ->assertSee(__('experiments::ui.check_ledger_openings'));
        $this->actingAs($root)->get(route('gov.experiments.accounts', $run))->assertOk()->assertDownload();
        foreach (app(RecordRegistry::class)->ids($run, 'locations') as $officeId) {
            $this->assertTrue(DB::table('custom_service_requests')->join('custom_service_request_items', 'custom_service_request_items.request_id', '=', 'custom_service_requests.id')
                ->join('models', 'models.id', '=', 'custom_service_request_items.requested_id')->where('custom_service_request_items.requested_type', 'asset_model')
                ->where('custom_service_requests.office_id', $officeId)->where('models.name', 'like', '%Office Chairs%')->exists());
        }
        $basket = DraftBasket::where('user_id', $employee->id)->first();
        if ($basket) {
            foreach ($basket->items as $item) {
                $this->assertNotEmpty(RequestableFactory::make($item->requested_type, $item->requested_id)->getDisplayName());
            }
        }
        $before = app(RecordRegistry::class)->counts($run);
        $sequences = DB::table('gov_store_document_sequences')->orderBy('prefix')->get()->toArray();
        app(BangladeshScenario::class)->populate($run, $root, $run->password);
        $this->assertSame($before, app(RecordRegistry::class)->counts($run));
        $wiper = app(ExperimentWiper::class);
        $preview = $wiper->preview($run, 'dataset', $root);
        $this->assertEmpty($preview['blockers'], json_encode($preview['blockers']));
        $this->actingAs($root)->get(route('gov.experiments.preview', $run))->assertOk();
        $orphan = 'gov-experiments/'.$run->id.'/documents/rolled-back-example.txt';
        Storage::disk('local')->put($orphan, 'Experiment file from a rolled-back unit');
        $manager->requestWipe($run, $root, 'dataset', $run->label, $preview['fingerprint']);
        $this->assertSame('wiped', $run->refresh()->status);
        $this->assertSame([], app(RecordRegistry::class)->counts($run));
        $this->assertFalse(DB::table('gov_store_ledger_openings')->whereIn('location_id', $officeIds)->exists());
        $this->assertEquals($sequences, DB::table('gov_store_document_sequences')->orderBy('prefix')->get()->toArray());
        $this->assertTrue(DB::table('companies')->where('id', $outside->id)->exists());
        $this->assertTrue(DB::table('users')->where('id', $root->id)->exists());
        $this->assertSame(8, DB::table('gov_geo_areas')->where('GeoLevel', 1)->count());
        $this->assertSame([], Storage::disk('local')->allFiles('gov-experiments/'.$run->id.'/documents'));
    }

    private function root(): User
    {
        return User::factory()->create(['permissions' => json_encode(['superuser' => '1']), 'activated' => true, 'location_id' => null, 'company_id' => null]);
    }

    public function test_account_updates_are_owned_unique_and_idempotent(): void
    {
        $root = $this->root();
        $rootCredentials = [$root->username, $root->password];
        $manager = app(ExperimentManager::class);
        $run = $manager->populate($root, 'quick');
        $run->update(['status' => 'ready', 'password' => 'old-fixture-password']);
        $people = [];
        foreach (range(1, 2) as $index) {
            $person = User::factory()->create(['first_name' => 'Shamima', 'last_name' => 'Nasrin', 'display_name' => 'শামীমা নাসরিন', 'username' => 'legacy-fixture-'.Str::uuid(), 'password' => Hash::make('old-fixture-password')]);
            app(RecordRegistry::class)->record($run, 'users', $person->getAttributes(), 'person-'.($index - 1));
            $people[] = $person;
        }
        $outside = User::factory()->create(['username' => 'shamimanasrin'.$people[0]->id]);
        $outsideCredentials = [$outside->username, $outside->password];
        $this->assertSame(2, $manager->updateAccounts($run, $root));
        $this->assertSame('1234567890', $run->refresh()->password);
        $usernames = [];
        foreach ($people as $person) {
            $person->refresh();
            $this->assertMatchesRegularExpression('/^shamimanasrin[0-9]+$/', $person->username);
            $this->assertTrue(Hash::check('1234567890', $person->password));
            $this->assertTrue(Auth::guard()->validate(['username' => $person->username, 'password' => '1234567890', 'activated' => 1]));
            $usernames[] = $person->username;
        }
        $this->assertCount(2, array_unique($usernames));
        $this->assertSame($outsideCredentials, [$outside->refresh()->username, $outside->password]);
        $this->assertSame($rootCredentials, [$root->refresh()->username, $root->password]);
        $this->assertSame(2, $manager->updateAccounts($run, $root));
        $this->assertSame($usernames, array_map(fn ($person) => $person->refresh()->username, $people));
    }

    public function test_external_dependents_and_stale_previews_block_without_deleting_records(): void
    {
        $root = $this->root();
        $run = app(ExperimentManager::class)->populate($root, 'quick');
        $run->update(['status' => 'ready']);
        $company = Company::factory()->create(['name' => 'Owned '.Str::uuid(), 'created_by' => $root->id]);
        app(RecordRegistry::class)->record($run, 'companies', $company->getAttributes());
        $outside = Company::factory()->create(['name' => 'External child '.Str::uuid(), 'parent_id' => $company->id, 'created_by' => $root->id]);
        $this->assertNotNull($outside->id);
        $wiper = app(ExperimentWiper::class);
        $this->assertNotEmpty($wiper->preview($run, 'dataset', $root)['blockers']);
        DB::table('companies')->where('id', $outside->id)->delete();
        $preview = $wiper->preview($run, 'dataset', $root);
        $this->assertEmpty($preview['blockers']);
        DB::table('companies')->where('id', $company->id)->update(['name' => 'Changed '.Str::uuid()]);
        try {
            $wiper->wipe($run, 'dataset', $root, $preview['fingerprint']);
            $this->fail('A stale preview was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertTrue(DB::table('companies')->where('id', $company->id)->exists());
        }
    }

    public function test_queued_job_rechecks_revoked_superuser_access_before_population(): void
    {
        $root = $this->root();
        $manager = app(ExperimentManager::class);
        $run = $manager->populate($root, 'quick');
        DB::table('users')->where('id', $root->id)->update(['permissions' => '{}']);
        try {
            $manager->dispatch($run, $root);
            $this->fail('Revoked access was accepted by the job.');
        } catch (AuthorizationException $e) {
            $this->assertSame('failed', $run->refresh()->status);
            $this->assertSame([], app(RecordRegistry::class)->counts($run));
        }
    }

    public function test_installation_reset_backs_up_and_preserves_only_protected_setup_and_actor(): void
    {
        Storage::fake('local');
        $root = $this->root();
        $manager = app(ExperimentManager::class);
        $run = $manager->populate($root, 'quick');
        $run->update(['status' => 'ready']);
        $outside = Company::factory()->create(['name' => 'Reset business '.Str::uuid(), 'created_by' => $root->id]);
        $wiper = app(ExperimentWiper::class);
        $preview = $wiper->preview($run, 'database', $root);
        $this->assertEmpty($preview['blockers'], json_encode($preview['blockers']));
        $manager->requestWipe($run, $root, 'database', $preview['confirmation'], $preview['fingerprint']);
        $this->assertSame('wiped', $run->refresh()->status);
        $this->assertSame(1, DB::table('users')->count());
        $this->assertSame(0, DB::table('companies')->count());
        $this->assertTrue(DB::table('users')->where('id', $root->id)->exists());
        $this->assertSame(8, DB::table('gov_geo_areas')->where('GeoLevel', 1)->count());
        $action = DB::table('gov_experiment_actions')->where('run_id', $run->id)->where('action', 'wipe')->first();
        $backup = json_decode($action->details, true)['backup'];
        $snapshot = [];
        foreach (explode("\n", trim(Storage::disk('local')->get($backup))) as $line) {
            $record = json_decode(Crypt::decryptString($line), true);
            if ($record['kind'] === 'rows') {
                $snapshot[$record['table']] = array_merge($snapshot[$record['table']] ?? [], $record['rows']);
            }
        }
        $this->assertNotEmpty($snapshot['companies']);
        $this->assertGreaterThan(1, count($snapshot['users']));
        $this->assertSame(0, DB::table('gov_experiment_records')->count());
    }

    public function test_stopped_job_recovery_is_locked_and_removes_only_its_queued_job(): void
    {
        $root = $this->root();
        $manager = app(ExperimentManager::class);
        $run = $manager->populate($root, 'quick');
        config(['govstore-experiments.connection' => 'govstore-experiments']);
        $manager->dispatch($run, $root);
        $this->assertSame(1, DB::table('gov_experiment_jobs')->count());
        $lockName = 'gov-experiment-'.substr(hash('sha256', DB::connection()->getDatabaseName()), 0, 40);
        $connection = DB::connection()->getConfig();
        $other = new \PDO('mysql:host='.$connection['host'].';port='.$connection['port'].';dbname='.$connection['database'], $connection['username'], $connection['password']);
        $statement = $other->prepare('SELECT GET_LOCK(?, 0)');
        $statement->execute([$lockName]);
        $this->assertSame(1, (int) $statement->fetchColumn());
        try {
            try {
                $manager->recover($run, $root);
                $this->fail('Recovery interrupted a running worker.');
            } catch (\RuntimeException $e) {
                $this->assertSame('pending', $run->refresh()->status);
            }
        } finally {
            $statement = $other->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$lockName]);
        }
        $manager->recover($run, $root);
        $this->assertSame('failed', $run->refresh()->status);
        $this->assertSame(0, DB::table('gov_experiment_jobs')->count());
        $second = $manager->populate($root, 'quick');
        $this->assertNotSame($run->report['record_prefix'], $second->report['record_prefix']);
        $manager->recover($second, $root);
    }

    public function test_failed_stock_phase_resumes_with_legacy_component_history_without_double_counting(): void
    {
        Storage::fake('local');
        $root = $this->root();
        $manager = app(ExperimentManager::class);
        $run = $manager->populate($root, 'quick');
        $blockedOpening = \Mockery::mock(ExperimentLedgerOpening::class);
        $blockedOpening->shouldReceive('open')->once()->andThrow(new \RuntimeException('Simulated stock interruption'));
        app()->instance(ExperimentLedgerOpening::class, $blockedOpening);
        try {
            $manager->dispatch($run, $root);
            $this->fail('Expected stock phase interruption.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Simulated stock interruption', $e->getMessage());
            $this->assertSame('failed', $run->refresh()->status);
            $this->assertSame('stock', $run->phase);
        } finally {
            app()->forgetInstance(ExperimentLedgerOpening::class);
        }
        $office = Location::withoutGlobalScopes()->findOrFail($run->report['units']['office-0']);
        $keeperId = DB::table('gov_office_responsibilities')->where('location_id', $office->id)->where('role_slug', 'storekeeper')->value('user_id');
        $keeper = User::withoutGlobalScopes()->findOrFail($keeperId);
        $components = Component::withoutGlobalScopes()->where('location_id', $office->id)->get();
        $records = app(RecordRegistry::class);
        $legacy = app(ActorContext::class)->run($keeper, $office, function () use ($run, $office, $keeper, $components, $records) {
            $document = app(GoodsReceiptService::class)->saveDraft([
                'reference_no' => $run->report['record_prefix'].'-OPENING-0', 'reference_date' => $run->anchor_date->format('Y-m-d'),
            ], $components->map(fn ($item) => ['type' => 'component', 'id' => $item->id, 'qty' => 1, 'unit_cost' => 0])->all(), $keeper->id);
            $records->record($run, 'gov_documents', $document->getAttributes());
            foreach ($components as $item) {
                $movement = InventoryMovement::create(['stockable_type' => 'component', 'stockable_id' => $item->id,
                    'movement_type' => 'IN', 'quantity' => 1, 'balance_after' => 1, 'document_type' => Document::class,
                    'document_id' => $document->id, 'company_id' => $office->company_id, 'location_id' => $office->id, 'created_by' => $keeper->id]);
                $records->record($run, 'gov_inventory_movements', $movement->getAttributes());
            }
            $document->transitionTo(DocumentState::POSTED, $keeper->id, 'Legacy component fixture opening');

            return $document;
        }, 'storekeeper');
        $legacyAttributes = $legacy->refresh()->getAttributes();
        $legacyMovements = DB::table('gov_inventory_movements')->where('document_id', $legacy->id)->orderBy('id')->get()->toArray();
        $report = $run->report;
        $report['units']['opening-components-0'] = $legacy->id;
        $run->update(['report' => $report]);

        $manager->resume($run, $root);
        $this->assertSame('ready', $run->refresh()->status, $run->error ?? json_encode($run->report['verification'] ?? []));
        $this->assertSame($legacyAttributes, $legacy->refresh()->getAttributes());
        $this->assertEquals($legacyMovements, DB::table('gov_inventory_movements')->where('document_id', $legacy->id)->orderBy('id')->get()->toArray());
        $opening = DB::table('gov_store_ledger_openings')->where('location_id', $office->id)->first();
        $this->assertNotSame($legacy->id, $opening->document_id);
        $this->assertSame(0, DB::table('gov_inventory_movements')->where('document_id', $opening->document_id)->count());
        foreach ($components as $component) {
            $this->assertEquals(101, $component->refresh()->qty);
            $this->assertEquals(101, DB::table('gov_inventory_movements')->where('stockable_type', 'component')
                ->where('stockable_id', $component->id)->sum('quantity'));
        }
        $this->assertSame(13, $records->counts($run)['gov_documents']);
        $before = $records->counts($run);
        app(BangladeshScenario::class)->populate($run, $root, $run->password);
        $this->assertSame($before, $records->counts($run));
        $preview = app(ExperimentWiper::class)->preview($run, 'dataset', $root);
        $this->assertEmpty($preview['blockers'], json_encode($preview['blockers']));
        $manager->requestWipe($run, $root, 'dataset', $run->label, $preview['fingerprint']);
        $this->assertSame('wiped', $run->refresh()->status);
        $this->assertFalse(DB::table('gov_store_ledger_openings')->where('location_id', $office->id)->exists());
    }

    private function openingFixture(): array
    {
        $root = $this->root();
        $run = app(ExperimentManager::class)->populate($root, 'quick');
        $run->update(['status' => 'failed']);
        $company = Company::factory()->create();
        $office = Location::factory()->create(['company_id' => $company->id]);
        $keeper = User::factory()->create(['company_id' => $company->id, 'location_id' => $office->id,
            'activated' => true, 'permissions' => '{}']);
        $this->actingAs($root);
        OfficeMembership::create(['user_id' => $keeper->id, 'location_id' => $office->id, 'is_home_office' => true, 'status' => 'active']);
        OfficeResponsibility::create(['user_id' => $keeper->id, 'location_id' => $office->id, 'role_slug' => 'storekeeper']);
        foreach ([$company, $office, $keeper] as $model) {
            app(RecordRegistry::class)->record($run, $model->getTable(), $model->getAttributes());
        }

        return [$run, $office, $keeper];
    }

    public function test_zero_opening_is_idempotent_and_preserves_shared_context_and_actor(): void
    {
        [$run, $office, $keeper] = $this->openingFixture();
        $context = app(TenantContext::class);
        $context->locationId = 987;
        $context->configs = ['example' => (object) ['value' => 'preserved']];
        $before = clone $context;
        $this->actingAs($keeper);
        $opener = app(ExperimentLedgerOpening::class);
        $document = $opener->open($run, $office, $keeper);
        $this->assertSame('opening', $document->type);
        $this->assertSame(0, $document->items()->count());
        $this->assertSame(0, DB::table('gov_inventory_movements')->where('document_id', $document->id)->count());
        $this->assertSame($document->id, $opener->open($run, $office, $keeper)->id);
        $this->assertSame($context, app(TenantContext::class));
        $this->assertEquals($before, $context);
        $this->assertSame($keeper, Auth::user());
        $foreignOffice = Location::factory()->create(['company_id' => $office->company_id]);
        DB::table('gov_store_ledger_openings')->where('location_id', $office->id)->update(['location_id' => $foreignOffice->id]);
        $root = User::withoutGlobalScopes()->findOrFail($run->initiated_by);
        $preview = app(ExperimentWiper::class)->preview($run, 'dataset', $root);
        $this->assertContains('gov_store_ledger_openings: opening document and office ownership disagree', $preview['blockers']);
    }

    public function test_opening_rejects_foreign_offices_unassigned_actors_and_rolls_back_conflicting_stock(): void
    {
        [$run, $office, $keeper] = $this->openingFixture();
        $opener = app(ExperimentLedgerOpening::class);
        $foreignOffice = Location::factory()->create(['company_id' => $office->company_id]);
        try {
            $opener->open($run, $foreignOffice, $keeper);
            $this->fail('An unowned office was opened.');
        } catch (\RuntimeException $e) {
            $this->assertFalse(DB::table('gov_store_ledger_openings')->where('location_id', $foreignOffice->id)->exists());
        }
        OfficeResponsibility::where('location_id', $office->id)->delete();
        try {
            $opener->open($run, $office, $keeper);
            $this->fail('An ordinary office member opened the ledger.');
        } catch (AuthorizationException $e) {
            $this->assertFalse(DB::table('gov_store_ledger_openings')->where('location_id', $office->id)->exists());
        }
        OfficeResponsibility::create(['user_id' => $keeper->id, 'location_id' => $office->id, 'role_slug' => 'storekeeper']);
        $component = Component::factory()->create(['company_id' => $office->company_id, 'location_id' => $office->id, 'qty' => 1, 'created_by' => $keeper->id]);
        $conflict = Component::factory()->create(['company_id' => Company::factory()->create()->id, 'location_id' => $office->id, 'qty' => 1, 'created_by' => $keeper->id]);
        foreach ([$component, $conflict] as $item) {
            app(RecordRegistry::class)->record($run, 'components', $item->getAttributes());
        }
        $before = app(RecordRegistry::class)->counts($run);
        $auditCount = DB::table('action_logs')->where('item_type', Component::class)->where('item_id', $component->id)->count();
        try {
            $opener->open($run, $office, $keeper);
            $this->fail('Company-conflicting stock was opened.');
        } catch (\RuntimeException $e) {
            $this->assertSame('An experiment stock item belongs to another company.', $e->getMessage());
        }
        $this->assertSame($before, app(RecordRegistry::class)->counts($run));
        $this->assertFalse(DB::table('gov_store_ledger_openings')->where('location_id', $office->id)->exists());
        $this->assertFalse(DB::table('gov_documents')->where('location_id', $office->id)->exists());
        $this->assertFalse(DB::table('gov_inventory_movements')->where('location_id', $office->id)->exists());
        $this->assertEquals(1, $component->refresh()->qty);
        $this->assertSame($auditCount, DB::table('action_logs')->where('item_type', Component::class)->where('item_id', $component->id)->count());
    }

    public function test_opening_rejects_inconsistent_existing_ledger_without_rewriting_history(): void
    {
        [$run, $office, $keeper] = $this->openingFixture();
        $component = Component::factory()->create(['company_id' => $office->company_id, 'location_id' => $office->id, 'qty' => 1, 'created_by' => $keeper->id]);
        $records = app(RecordRegistry::class);
        $records->record($run, 'components', $component->getAttributes());
        $movement = InventoryMovement::create(['stockable_type' => 'component', 'stockable_id' => $component->id,
            'movement_type' => 'IN', 'quantity' => 1, 'balance_after' => 2, 'document_type' => Document::class,
            'document_id' => (string) Str::uuid(), 'company_id' => $office->company_id, 'location_id' => $office->id, 'created_by' => $keeper->id]);
        $records->record($run, 'gov_inventory_movements', $movement->getAttributes());
        try {
            app(ExperimentLedgerOpening::class)->open($run, $office, $keeper);
            $this->fail('An inconsistent ledger chain was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Review inconsistent experiment ledger balances before opening this office.', $e->getMessage());
        }
        $this->assertEquals(2, $movement->refresh()->balance_after);
        $this->assertEquals(1, $component->refresh()->qty);
        $this->assertFalse(DB::table('gov_store_ledger_openings')->where('location_id', $office->id)->exists());
        $this->assertFalse(DB::table('gov_documents')->where('location_id', $office->id)->exists());
    }

    public function test_live_bridge_uses_selected_database_exported_credentials_and_active_membership_without_writes(): void
    {
        [$run, $office, $keeper] = $this->openingFixture();
        $run->update(['status' => 'ready']);
        $connection = DB::connection()->getConfig();
        $target = ['baseUrl' => 'http://target.example.test', 'environment' => 'testing', 'accessMode' => 'enforce',
            'database' => ['driver' => 'mysql', 'host' => $connection['host'], 'port' => $connection['port'],
                'database' => DB::connection()->getDatabaseName(), 'username' => $connection['username'], 'password' => $connection['password']]];
        $password = 'Isolated-Target-Bridge-Canary';
        $keeper->forceFill(['password' => Hash::make($password)])->saveQuietly();
        $call = function (string $action, array $input = []) use ($target): array {
            $process = new \Symfony\Component\Process\Process([PHP_BINARY, '-d', 'xdebug.mode=off',
                base_path('scripts/live-tests/bridge/live-test-adapter.php'), $action, '--stdin']);
            $process->setInput(json_encode([...$input, '_target' => $target]));
            $process->run();
            $result = json_decode($process->getOutput(), true);
            $this->assertIsArray($result, 'The adapter did not return a JSON response. Raw output withheld.');

            return $result;
        };
        $preflight = $call('preflight');
        $this->assertSame($target['database']['database'], $preflight['database']);
        $this->assertSame('http://target.example.test', $preflight['appUrl']);
        $this->assertSame('selected-target-file', $preflight['configurationSource']);
        $before = $call('fingerprint');
        $definition = ['datasetId' => $run->id, 'office' => ['id' => $office->id],
            'actors' => ['keeper' => ['capabilities' => ['allOf' => ['storeops.documents.view']]]],
            'fixtureCredentials' => [$keeper->username => $password]];
        $resolved = $call('resolveContext', $definition);
        $this->assertSame('success', $resolved['status']);
        $this->assertSame($keeper->id, $resolved['actors']['keeper']['id']);
        $this->assertSame($office->id, $resolved['office']['id']);
        $identity = $call('verifyAccount', ['username' => $keeper->username, 'password' => $password]);
        $this->assertSame($keeper->id, $identity['id']);
        $this->assertFalse($identity['isSuperuser']);
        $this->assertSame($keeper->id, $call('verifyAccount', ['userId' => $keeper->id, 'password' => $password])['id']);
        $this->assertSame('credentials.invalid-user-id', $call('verifyAccount', ['userId' => 0, 'password' => $password])['code']);
        $definition['fixtureCredentials'][$keeper->username] = 'Incorrect-Target-Canary';
        $this->assertSame('fixtures.unsatisfied-requirements', $call('resolveContext', $definition)['code']);
        $definition['fixtureCredentials'][$keeper->username] = $password;
        $definition['datasetId'] = (string) Str::uuid();
        $this->assertSame('fixtures.unsatisfied-requirements', $call('resolveContext', $definition)['code']);
        $this->assertSame($before, $call('fingerprint'));
        $definition['datasetId'] = $run->id;
        OfficeMembership::where('user_id', $keeper->id)->where('location_id', $office->id)->update(['valid_until' => today()->subDay()]);
        $this->assertSame('fixtures.unsatisfied-requirements', $call('resolveContext', $definition)['code']);
    }
}
