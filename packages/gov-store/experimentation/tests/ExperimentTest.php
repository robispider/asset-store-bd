<?php

namespace Tests\Experimentation;

use App\Models\Asset;
use App\Models\Company;
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
use GovStore\Experimentation\Services\ExperimentWiper;
use GovStore\Experimentation\Services\RecordRegistry;
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
        $this->actingAs($root)->get(route('gov.experiments.show', $run))->assertOk();
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
}
