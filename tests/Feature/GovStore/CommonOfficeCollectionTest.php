<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use GovStore\Classification\Http\Controllers\BulkAdoptionController;
use GovStore\Classification\Http\Controllers\CollectionDiscoveryController;
use GovStore\Classification\Models\CatalogCollection;
use GovStore\Classification\Services\BulkAdoptionService;
use GovStore\Classification\Services\CatalogSearchService;
use GovStore\Classification\Services\Defaults\CommonOfficeCollectionV1;
use GovStore\TenantScope\Contexts\TenantContext;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Mockery;
use RuntimeException;

/** All migrations and adoption writes in this class use SQLite memory only. */
class CommonOfficeCollectionTest extends TestCase
{
    public function createApplication()
    {
        foreach (['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => ':memory:', 'CACHE_DRIVER' => 'array',
            'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync'] as $key => $value) {
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'cache.default' => 'array', 'govstore-access.mode' => 'enforce',
            'logging.default' => 'testing', 'logging.channels.testing' => ['driver' => 'monolog', 'handler' => \Monolog\Handler\NullHandler::class]]);
        DB::purge('sqlite');
        app()->setLocale('en-US');
        app(\GovStore\TenantScope\Services\SchemaKnowledge::class)->clear();
        app(TenantContext::class)->reset();
        foreach (['companies', 'users'] as $name) {
            Schema::create($name, fn (Blueprint $table) => $table->increments('id'));
        }
        Schema::create('categories', function (Blueprint $table) {
            $table->increments('id'); $table->string('name'); $table->string('category_type');
            $table->boolean('checkin_email')->default(false); $table->boolean('require_acceptance')->default(false);
            $table->boolean('use_default_eula')->default(false); $table->timestamp('deleted_at')->nullable(); $table->timestamps();
        });
        foreach (['assets', 'consumables', 'accessories', 'components', 'models'] as $name) {
            Schema::create($name, fn (Blueprint $table) => $table->increments('id'));
        }
        Schema::create('gov_tenant_scope_mappings', function (Blueprint $table) {
            $table->increments('id'); $table->string('reference_type'); $table->integer('reference_id');
            $table->string('scope_type'); $table->integer('scope_id'); $table->boolean('is_active'); $table->timestamps();
            $table->unique(['reference_type', 'reference_id', 'scope_type', 'scope_id']);
        });
        Schema::create('gov_location_profiles', function (Blueprint $table) {
            $table->increments('id'); $table->integer('location_id'); $table->integer('office_admin_id');
        });
        Schema::create('gov_office_responsibilities', function (Blueprint $table) {
            $table->increments('id'); $table->integer('user_id'); $table->integer('location_id'); $table->string('role_slug');
        });
        Schema::create('gov_company_admins', function (Blueprint $table) {
            $table->increments('id'); $table->integer('user_id'); $table->integer('company_id');
        });
        Schema::create('gov_ict_jurisdictions', function (Blueprint $table) {
            $table->increments('id'); $table->integer('user_id');
        });
        DB::table('companies')->insert(['id' => 20]);
        DB::table('users')->insert(['id' => 1]);
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
    }

    private function migrate(): void
    {
        $this->assertSame(0, Artisan::call('migrate', [
            '--path' => 'packages/gov-store/classification/src/database/migrations', '--force' => true,
        ]), Artisan::output());
    }

    private function actor(string $role = 'office_admin', int $office = 10): void
    {
        $context = app(TenantContext::class);
        $context->reset();
        $context->isActive = true;
        $context->locationId = $office;
        $context->companyId = 20;
        $context->isCompanyAdmin = $role === 'company_admin';
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->shouldReceive('isSuperUser')->andReturn(false);
        $user->shouldReceive('hasAccess')->andReturn($role === 'native_admin');
        auth()->setUser($user);
        DB::table('gov_location_profiles')->delete();
        DB::table('gov_office_responsibilities')->delete();
        DB::table('gov_company_admins')->delete();
        if ($role === 'office_admin') {
            DB::table('gov_location_profiles')->insert(['location_id' => $office, 'office_admin_id' => 1]);
        } elseif ($role === 'storekeeper') {
            DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => $office, 'role_slug' => 'storekeeper']);
        } elseif ($role === 'company_admin') {
            DB::table('gov_company_admins')->insert(['company_id' => 20, 'user_id' => 1]);
        }
    }

    private function collectionId(): int
    {
        return (int) DB::table('gov_catalog_collections')->where('key', CommonOfficeCollectionV1::KEY)->value('id');
    }

    private function preview(array $codes): array
    {
        return app(BulkAdoptionController::class)->preview(Request::create('/', 'POST', [
            'codes' => $codes, 'collection_id' => $this->collectionId(),
        ]), app(TenantContext::class))->getData(true);
    }

    public function test_migrate_installs_one_complete_collection_on_an_empty_catalog_without_adoption_or_stock(): void
    {
        $this->migrate();
        $this->assertSame(1, DB::table('gov_catalog_collections')->count());
        $this->assertSame(114, DB::table('gov_catalog_collection_nodes')->count());
        $this->assertSame(193, DB::table('gov_catalog_nodes')->count());
        $this->assertSame(114, DB::table('gov_catalog_enrichments')->count());
        $this->assertSame(0, DB::table('gov_finance_items')->count());
        $this->assertSame(1, DB::table('gov_catalog_import_history')->count());
        $this->assertNull(DB::table('gov_catalog_import_history')->value('user_id'));
        foreach (['categories', 'assets', 'consumables', 'accessories', 'components', 'models', 'gov_tenant_scope_mappings', 'gov_office_starter_runs'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
        $this->assertNotContains('Bangladesh Government Common Office', config('starter_templates.office_types.default'));
        $this->migrate();
        $this->assertSame(1, DB::table('gov_catalog_import_history')->count());
        $this->assertSame(114, DB::table('gov_catalog_collection_nodes')->count());
    }

    public function test_bundle_matches_the_bundled_unspsc_source_and_has_complete_ancestor_paths(): void
    {
        $this->migrate();
        $source = fopen(base_path('packages/gov-store/classification/src/database/data/compiled_nodes.csv'), 'rb');
        $columns = fgetcsv($source, 0, ',', '"', '');
        $installed = DB::table('gov_catalog_nodes')->get()->keyBy('code');
        $matched = 0;
        while (($line = fgetcsv($source, 0, ',', '"', '')) !== false) {
            $row = array_combine($columns, $line);
            if ($node = $installed[$row['code']] ?? null) {
                foreach ($row as $key => $value) {
                    $this->assertSame((string) $value, (string) $node->$key, $row['code'].' '.$key);
                }
                $matched++;
            }
        }
        fclose($source);
        $this->assertSame(193, $matched);
    }

    public function test_reinstall_preserves_local_edits_mappings_and_collection_identity_without_duplicates(): void
    {
        $this->migrate();
        DB::table('gov_catalog_nodes')->where('code', '43211507')->update(['title_en' => 'Locally named computer', 'version' => 'local-version']);
        DB::table('gov_catalog_enrichments')->where('code', '43211507')->update(['title_bn' => 'স্থানীয় নাম', 'local_notes' => 'Preserve this note']);
        DB::table('gov_catalog_collections')->where('id', $this->collectionId())->update(['name' => 'Our collection', 'is_active' => false]);
        DB::table('gov_catalog_collection_nodes')->where('code', '43211507')->update(['category_type' => 'accessory']);
        $synonyms = DB::table('gov_catalog_synonyms')->count();
        (new CommonOfficeCollectionV1)->install();
        $this->assertSame(1, DB::table('gov_catalog_collections')->count());
        $this->assertSame('Our collection', DB::table('gov_catalog_collections')->value('name'));
        $this->assertSame(0, (int) DB::table('gov_catalog_collections')->value('is_active'));
        $this->assertSame('Locally named computer', DB::table('gov_catalog_nodes')->where('code', '43211507')->value('title_en'));
        $this->assertSame('local-version', DB::table('gov_catalog_nodes')->where('code', '43211507')->value('version'));
        $this->assertSame('Preserve this note', DB::table('gov_catalog_enrichments')->where('code', '43211507')->value('local_notes'));
        $this->assertSame('accessory', DB::table('gov_catalog_collection_nodes')->where('code', '43211507')->value('category_type'));
        $this->assertSame($synonyms, DB::table('gov_catalog_synonyms')->count());
        $this->assertSame(1, DB::table('gov_catalog_import_history')->count());
    }

    public function test_changed_csv_is_rejected_before_any_writes(): void
    {
        $this->migrate();
        $source = base_path('packages/gov-store/classification/src/database/data/office-defaults/v1');
        $directory = sys_get_temp_dir().'/govstore-common-office-'.bin2hex(random_bytes(6));
        mkdir($directory);
        try {
            foreach (['manifest.json', 'collections.csv', 'items.csv', 'nodes.csv'] as $file) {
                copy($source.'/'.$file, $directory.'/'.$file);
            }
            file_put_contents($directory.'/items.csv', "\n", FILE_APPEND);
            try {
                (new CommonOfficeCollectionV1)->install($directory);
                $this->fail('Changed CSV was accepted.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('CSV bundle', $e->getMessage());
            }
            $this->assertSame(1, DB::table('gov_catalog_import_history')->count());
        } finally {
            foreach (['manifest.json', 'collections.csv', 'items.csv', 'nodes.csv'] as $file) {
                unlink($directory.'/'.$file);
            }
            rmdir($directory);
        }
    }

    public function test_conflicting_existing_node_rolls_back_earlier_reference_inserts_and_the_whole_collection(): void
    {
        // Only install the pre-existing schema; exercise the new data installer against a conflict.
        foreach (['2024_04_01_000000_create_gov_catalog_tables', '2026__08_04_create_gov_catalog_collections_table',
            '2026__10_26_000001_add_collection_item_defaults'] as $name) {
            (require base_path('packages/gov-store/classification/src/database/migrations/'.$name.'.php'))->up();
        }
        DB::table('gov_catalog_nodes')->insert(['scheme' => 'OTHER', 'version' => 'existing', 'code' => '56101720',
            'level' => 4, 'title_en' => 'Existing conflict', 'hid' => '/56101720/']);
        try {
            (new CommonOfficeCollectionV1)->install();
            $this->fail('Conflicting installed node was overwritten.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('conflicts', $e->getMessage());
        }
        $this->assertSame(1, DB::table('gov_catalog_nodes')->count());
        $this->assertSame('OTHER', DB::table('gov_catalog_nodes')->value('scheme'));
        $this->assertSame(0, DB::table('gov_catalog_collections')->count());
        $this->assertSame(0, DB::table('gov_catalog_import_history')->count());
    }

    public function test_office_chooses_a_mixed_subset_and_existing_types_survive_repeated_and_second_office_adoption(): void
    {
        $this->migrate();
        $this->actor();
        $preview = $this->preview(['43211507', '44121704', '43211706', '43201830']);
        $this->assertTrue($preview['success']);
        $this->assertSame('Office', $preview['target_scope']);
        $types = array_column($preview['summary']['new'], 'category_type', 'code');
        $this->assertSame('asset', $types['43211507']);
        $this->assertSame('consumable', $types['44121704']);
        $this->assertSame('accessory', $types['43211706']);
        $this->assertSame('component', $types['43201830']);
        $items = array_map(fn ($code, $type) => ['code' => (string) $code, 'category_type' => $type], array_keys($types), array_values($types));
        $result = app(BulkAdoptionController::class)->execute(Request::create('/', 'POST', ['items' => $items]), app(TenantContext::class))->getData(true);
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame(4, DB::table('categories')->count());
        $this->assertSame(['location'], DB::table('gov_tenant_scope_mappings')->distinct()->pluck('scope_type')->all());
        $this->assertSame([10], DB::table('gov_tenant_scope_mappings')->distinct()->pluck('scope_id')->all());
        $again = $this->preview(array_map('strval', array_keys($types)));
        $this->assertCount(4, $again['summary']['skipped']);
        $this->assertSame(4, app(CollectionDiscoveryController::class)->show($this->collectionId(), app(TenantContext::class))->getData()['adoptedCount']);
        $this->actor('office_admin', 30);
        $this->assertSame(0, app(CollectionDiscoveryController::class)->show($this->collectionId(), app(TenantContext::class))->getData()['adoptedCount']);
        $linked = $this->preview(['43211507']);
        $this->assertSame('asset', $linked['summary']['link'][0]['category_type']);
        // Even a different submitted type cannot rewrite the existing classification.
        app(BulkAdoptionController::class)->execute(Request::create('/', 'POST', ['items' => [['code' => '43211507', 'category_type' => 'consumable']]]), app(TenantContext::class));
        $this->assertSame('asset', DB::table('categories')->where('name', 'Desktop computer')->value('category_type'));
        $this->assertSame(4, DB::table('categories')->count());
        $this->assertSame(1, app(CollectionDiscoveryController::class)->show($this->collectionId(), app(TenantContext::class))->getData()['adoptedCount']);
        foreach (['assets', 'consumables', 'accessories', 'components', 'models'] as $table) {
            $this->assertSame(0, DB::table($table)->count());
        }
    }

    public function test_collection_preview_rejects_unknown_ids_inactive_collections_and_nonmembers(): void
    {
        $this->migrate();
        $this->actor();
        foreach ([['codes' => ['43211507'], 'collection_id' => 999], ['codes' => ['99999999'], 'collection_id' => $this->collectionId()],
            ['codes' => ['43000000'], 'collection_id' => $this->collectionId()]] as $data) {
            try {
                app(BulkAdoptionController::class)->preview(Request::create('/', 'POST', $data), app(TenantContext::class));
                $this->fail('Invalid collection input was accepted.');
            } catch (ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        DB::table('gov_catalog_collections')->update(['is_active' => false]);
        $this->expectException(ValidationException::class);
        $this->preview(['43211507']);
    }

    public function test_collection_choice_preserves_existing_adoption_permissions(): void
    {
        $this->migrate();
        foreach (['employee' => false, 'native_admin' => false, 'office_admin' => true, 'storekeeper' => true, 'company_admin' => true] as $role => $allowed) {
            $this->actor($role);
            $this->assertSame($allowed, app(GovAccess::class)->decide(auth()->user(), 'catalog.office.adopt')->allowed, $role);
        }
    }

    public function test_bangla_and_local_aliases_are_searchable_and_collection_names_are_localized(): void
    {
        $this->migrate();
        foreach (['আলমারি' => '56101530', 'Laptop' => '43211503', 'খাতা' => '14111514'] as $term => $code) {
            $this->assertContains($code, app(CatalogSearchService::class)->search($term)->pluck('code')->all());
        }
        $collection = CatalogCollection::findOrFail($this->collectionId());
        app()->setLocale('bn-BD');
        $this->assertSame('বাংলাদেশ সরকারি অফিসের সাধারণ পণ্য', $collection->display_name);
        app()->setLocale('en-US');
        $this->assertSame('Bangladesh Government Common Office', $collection->display_name);
        $en = require base_path('packages/gov-store/classification/src/resources/lang/en-US/collections.php');
        $bn = require base_path('packages/gov-store/classification/src/resources/lang/bn-BD/collections.php');
        $this->assertSame(array_keys(\Illuminate\Support\Arr::dot($en)), array_keys(\Illuminate\Support\Arr::dot($bn)));
    }

    public function test_company_preview_does_not_mistake_an_office_adoption_for_ministry_adoption(): void
    {
        $this->migrate();
        $this->actor();
        app(BulkAdoptionService::class)->execute([['code' => '43211507', 'category_type' => 'asset']], 'location', 10, 1);
        $this->actor('company_admin');
        $preview = $this->preview(['43211507']);
        $this->assertCount(1, $preview['summary']['link']);
        $this->assertCount(0, $preview['summary']['skipped']);
        $this->assertSame(0, app(CollectionDiscoveryController::class)->show($this->collectionId(), app(TenantContext::class))->getData()['adoptedCount']);
    }

    public function test_bangla_collection_search_excludes_inactive_collections(): void
    {
        $this->migrate();
        app()->setLocale('bn-BD');
        $search = app(CatalogSearchService::class);
        $this->assertCount(1, $search->searchUniversal('সরকারি অফিস', 10)['collections']);
        DB::table('gov_catalog_collections')->where('id', $this->collectionId())->update(['is_active' => false]);
        $this->assertCount(0, $search->searchUniversal('সরকারি অফিস', 10)['collections']);
        $this->assertCount(0, $search->searchUniversal('Bangladesh', 10)['collections']);
    }

    public function test_collection_blade_templates_compile_to_valid_php_and_the_dialog_renders_in_both_languages(): void
    {
        foreach (['discover/collections/index', 'discover/collections/show', 'adopt/partials/bulk-preview'] as $name) {
            $path = base_path('packages/gov-store/classification/src/resources/views/'.$name.'.blade.php');
            $temporary = tempnam(sys_get_temp_dir(), 'gs-collection-blade-');
            try {
                file_put_contents($temporary, app('blade.compiler')->compileString(file_get_contents($path)));
                $process = proc_open([PHP_BINARY, '-l', $temporary], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                $output = stream_get_contents($pipes[1]).stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                $this->assertSame(0, proc_close($process), $output);
            } finally {
                unlink($temporary);
            }
        }
        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale);
            $html = view('gov-classification::adopt.partials.bulk-preview')->render();
            $this->assertStringContainsString(e(__('classification::collections.bulk.title')), $html);
            $this->assertStringNotContainsString('<x-gs::', $html);
            $this->assertStringNotContainsString('classification::collections.', $html);
        }
    }
}
