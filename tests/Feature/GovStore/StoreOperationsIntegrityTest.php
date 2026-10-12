<?php

namespace Tests\Feature\GovStore;

use App\Models\Consumable;
use App\Models\User;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Models\InventoryMovement;
use GovStore\StoreOperations\Services\LedgerPostingService;
use GovStore\StoreOperations\Services\LedgerStockGuard;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use GovStore\StoreOperations\Services\ProfileSchemaUpgrade;
use GovStore\StoreOperations\Services\TransferPostingService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Real migration upgrades and two-office transfer checks, isolated SQLite only. */
class StoreOperationsIntegrityTest extends StoreOperationsWorkflowTest
{
    private function identity(): User
    {
        config(['govstore-access.mode' => 'enforce']);
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('username'); $t->string('permissions');
            $t->boolean('activated'); $t->integer('company_id'); $t->integer('location_id'); $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('permission_groups', fn (Blueprint $t) => $t->increments('id'));
        Schema::create('users_groups', function (Blueprint $t) { $t->integer('user_id'); $t->integer('group_id'); });
        Schema::create('locations', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('company_id'); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('gov_office_memberships', function (Blueprint $t) {
            $t->increments('id'); $t->integer('user_id'); $t->integer('location_id'); $t->string('status');
            $t->boolean('is_home_office')->default(false); $t->date('valid_until')->nullable(); $t->timestamps();
        });
        foreach (['gov_company_admins' => ['user_id', 'company_id'], 'gov_ict_jurisdictions' => ['user_id'],
            'gov_location_profiles' => ['location_id', 'office_admin_id'], 'gov_office_responsibilities' => ['user_id', 'location_id', 'role_slug']] as $name => $columns) {
            Schema::create($name, function (Blueprint $t) use ($columns) { $t->increments('id'); foreach ($columns as $column) $t->string($column)->nullable(); });
        }
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        DB::table('users')->insert(['id' => 1, 'username' => 'transfer-test', 'permissions' => '{}', 'activated' => 1, 'company_id' => 20, 'location_id' => 10]);
        foreach ([10, 11] as $office) {
            DB::table('locations')->insert(['id' => $office, 'name' => 'Office '.$office, 'company_id' => 20]);
            DB::table('gov_office_memberships')->insert(['user_id' => 1, 'location_id' => $office, 'status' => 'active']);
            DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => $office, 'role_slug' => 'storekeeper']);
        }
        $context = app(TenantContext::class);
        $context->isActive = true;
        $context->allowedCompanyIds = [20];
        $actor = User::withoutGlobalScopes()->findOrFail(1);
        auth()->setUser($actor);
        return $actor;
    }

    private function opened(int $office, int $product, int $quantity): void
    {
        $document = Document::withoutGlobalScopes()->create(['document_number' => 'OB-'.$office, 'type' => 'opening',
            'status' => 'POSTED', 'location_id' => $office, 'company_id' => 20, 'created_by' => 1]);
        app(LedgerPostingService::class)->postMovement('consumable', $product, 'IN', $quantity, $document, 20, $office, 1);
        DB::table('gov_store_ledger_openings')->insert(['location_id' => $office, 'document_id' => $document->id,
            'opened_at' => now(), 'opened_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function transfer(): Document
    {
        $this->identity();
        DB::table('consumables')->where('id', 1)->update(['category_id' => 1]);
        DB::table('consumables')->insert(['id' => 2, 'name' => 'Paper', 'category_id' => 1, 'qty' => 5, 'location_id' => 11, 'company_id' => 20]);
        $this->opened(10, 1, 20); $this->opened(11, 2, 5);
        $document = Document::create(['document_number' => 'TR-TEST', 'type' => 'transfer', 'status' => 'DRAFT',
            'location_id' => 10, 'company_id' => 20, 'created_by' => 1, 'drafted_by' => 1,
            'destination_location_id' => 11, 'transfer_reason' => 'Share office supplies', 'compiled_profile_snapshot' => ['items' => []]]);
        $item = $document->items()->create(['product_type' => 'consumable', 'product_id' => 1, 'quantity' => 3]);
        $item->metadata()->create(['field_key' => 'destination_stockable_id', 'value' => '2', 'row_index' => 0]);
        return $document;
    }

    public function test_transfer_posts_paired_office_documents_and_projects_stock_once(): void
    {
        $document = $this->transfer();
        $events = Event::getFacadeRoot()->dispatcher;
        Event::swap($events);
        // Native audit is independently exercised by the request suite; retain the real quantity projection.
        $events->forget(\GovStore\StoreOperations\Events\InventoryMovementCreated::class);
        $events->listen(\GovStore\StoreOperations\Events\InventoryMovementCreated::class,
            [\GovStore\StoreOperations\Listeners\UpdateSnipeQuantity::class, 'handle']);
        app(PostingPipelineManager::class)->materialize($document, 1);
        $receipt = Document::withoutGlobalScopes()->where('source_document_id', $document->id)->firstOrFail();
        $this->assertSame('POSTED', $document->fresh()->status);
        $this->assertSame('receipt', $receipt->type); $this->assertSame(11, (int) $receipt->location_id);
        $this->assertSame([17, 8], DB::table('consumables')->orderBy('id')->pluck('qty')->map(fn ($n) => (int) $n)->all());
        $this->assertSame(4, InventoryMovement::withoutGlobalScopes()->count());
        $this->assertSame(17, (int) InventoryMovement::withoutGlobalScopes()->where('document_id', $document->id)->value('balance_after'));
        $this->assertSame(8, (int) InventoryMovement::withoutGlobalScopes()->where('document_id', $receipt->id)->value('balance_after'));
        $this->assertSame(10, app(TenantContext::class)->locationId);
        try { app(TransferPostingService::class)->post($document, 1); $this->fail('Transfer replay must fail.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { $this->assertSame(409, $e->getStatusCode()); }
        $this->assertSame(4, InventoryMovement::withoutGlobalScopes()->count());
    }

    public function test_destination_role_revocation_and_shadow_do_not_authorize_transfer(): void
    {
        $document = $this->transfer();
        config(['govstore-access.mode' => 'shadow']);
        DB::table('gov_office_responsibilities')->where('location_id', 11)->delete();
        try { app(TransferPostingService::class)->post($document, 1); $this->fail('Destination posting authority is required in shadow too.'); }
        catch (\Illuminate\Auth\Access\AuthorizationException $e) { $this->assertNotEmpty($e->getMessage()); }
        $this->assertSame(2, InventoryMovement::withoutGlobalScopes()->count());
        $this->assertSame('DRAFT', $document->fresh()->status);
        $this->assertSame(10, app(TenantContext::class)->locationId);
    }

    public function test_transfer_rejects_unrelated_destination_item_and_rolls_back_unopened_destination(): void
    {
        $document = $this->transfer();
        DB::table('consumables')->where('id', 2)->update(['name' => 'Different supplies']);
        try { app(TransferPostingService::class)->post($document, 1); $this->fail('Target identity mismatch must fail.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { $this->assertSame(404, $e->getStatusCode()); }
        DB::table('consumables')->where('id', 2)->update(['name' => 'Paper']);
        DB::table('gov_store_ledger_openings')->where('location_id', 11)->delete();
        try { app(TransferPostingService::class)->post($document, 1); $this->fail('Unopened destination must roll back the source movement.'); }
        catch (\Exception $e) { $this->assertStringContainsString('Opening stock', $e->getMessage()); }
        $this->assertSame(2, InventoryMovement::withoutGlobalScopes()->count());
        $this->assertSame(0, Document::withoutGlobalScopes()->where('source_document_id', $document->id)->count());
        $this->assertSame('DRAFT', $document->fresh()->status);
    }

    public function test_native_model_and_bulk_quantity_changes_are_blocked_after_opening(): void
    {
        $this->opened(10, 1, 20);
        $item = Consumable::withoutGlobalScopes()->findOrFail(1);
        $item->setValidating(false);
        $item->qty = 21;
        foreach ([fn () => $item->save(), fn () => Consumable::whereKey(1)->update(['qty' => 21]),
            fn () => Consumable::whereKey(1)->increment('qty'), fn () => Consumable::whereKey(1)->delete(),
            fn () => app(LedgerStockGuard::class)->assertNativeMovementAllowed($item)] as $action) {
            try { $action(); $this->fail('Native stock mutation must be rejected.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('qty', $e->errors()); }
        }
        $this->assertSame(20, (int) DB::table('consumables')->value('qty'));
        $this->assertSame(1, DB::table('consumables')->count());
        // Unopened offices retain the native path; unrelated metadata remains editable.
        DB::table('consumables')->insert(['id' => 2, 'name' => 'Unopened', 'qty' => 1, 'location_id' => 11, 'company_id' => 20]);
        $this->assertSame(1, Consumable::whereKey(2)->update(['qty' => 2]));
        $this->assertSame(1, Consumable::whereKey(1)->update(['name' => 'Paper renamed']));
    }

    public function test_additive_profile_upgrade_preserves_ids_capabilities_assignments_and_lineage(): void
    {
        // Simulate an installation stopped at the original core profile schema.
        Schema::create('gov_profiles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->integer('parent_id')->nullable(); $t->uuid('lineage_id')->nullable(); $t->timestamps(); });
        Schema::create('gov_capabilities', function (Blueprint $t) { $t->id(); $t->string('code'); $t->string('type'); });
        Schema::create('gov_profile_capabilities', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('profile_id'); $t->unsignedBigInteger('capability_id'); $t->json('config_payload')->nullable(); });
        DB::table('gov_profiles')->insert(['id' => 42, 'name' => 'Retained rule', 'lineage_id' => 'retained-lineage']);
        DB::table('gov_capabilities')->insert(['id' => 5, 'code' => 'require_quantity', 'type' => 'VALIDATION']);
        DB::table('gov_profile_capabilities')->insert(['id' => 77, 'profile_id' => 42, 'capability_id' => 5, 'config_payload' => '{"keep":true}']);
        (require base_path('packages/gov-store/store-operations/src/Database/migrations/2024_03_04_000000_create_plugin_profiles_table.php'))->up();
        (require base_path('packages/gov-store/store-operations/src/Database/migrations/2024_03_08_000000_create_gov_policy_catalog_tables.php'))->up();
        DB::table('gov_profile_assignments')->insert(['profile_id' => 42, 'target_type' => 'System', 'target_id' => 1]);
        ProfileSchemaUpgrade::catalog();
        $this->assertSame(1, DB::table('gov_profiles')->count());
        $this->assertSame('retained-lineage', DB::table('gov_profiles')->where('id', 42)->value('lineage_id'));
        $this->assertSame('require_quantity', DB::table('gov_profile_capabilities')->where('id', 77)->value('capability_code'));
        $this->assertSame('{"keep":true}', DB::table('gov_profile_capabilities')->where('id', 77)->value('config_payload'));
        $this->assertSame(42, (int) DB::table('gov_profile_assignments')->value('profile_id'));
        $this->assertSame('DRAFT', DB::table('gov_profiles')->where('id', 42)->value('status'));
        DB::table('gov_profile_capabilities')->insert(['profile_id' => 42, 'capability_code' => 'require_serial']);
        $this->assertSame(2, DB::table('gov_profile_capabilities')->count());
    }

    public function test_warranty_metadata_is_per_unit_and_rendered_with_saved_values(): void
    {
        $capability = app(\GovStore\StoreOperations\Capabilities\RequireWarrantyCapability::class);
        $this->assertSame([], $capability->validate(['meta' => [['warranty_months' => 0], ['warranty_months' => 12]]]));
        $this->assertNotEmpty($capability->validate(['meta' => [['warranty_months' => -1]]]));
        $this->assertNotEmpty($capability->validate(['meta' => [['serial_number' => 'missing-warranty']]]));
        $html = $capability->renderUI(null, ['row_index' => 2, 'quantity' => 2]);
        $this->assertStringContainsString('items[2][meta][1][warranty_months]', $html);
    }

    public function test_fresh_core_profiles_survive_plugin_and_catalog_migrations(): void
    {
        foreach (['gov_document_item_meta', 'gov_document_items', 'gov_documents'] as $table) Schema::drop($table);
        (require base_path('packages/gov-store/store-operations/src/Database/migrations/2024_03_02_000000_create_gov_core_document_engine_tables.php'))->up();
        $ids = DB::table('gov_profiles')->orderBy('id')->pluck('id')->all();
        (require base_path('packages/gov-store/store-operations/src/Database/migrations/2024_03_04_000000_create_plugin_profiles_table.php'))->up();
        (require base_path('packages/gov-store/store-operations/src/Database/migrations/2024_03_08_000000_create_gov_policy_catalog_tables.php'))->up();
        (require base_path('packages/gov-store/store-operations/src/Database/migrations/2024_04_01_000000_upgrade_policy_governance_schema.php'))->up();
        $this->assertSame($ids, DB::table('gov_profiles')->orderBy('id')->pluck('id')->all());
        $this->assertSame(4, DB::table('gov_profile_capabilities')->whereNotNull('capability_code')->count());
        $this->assertSame(2, DB::table('gov_requirement_definitions')->count());
        $this->assertSame(3, DB::table('gov_profiles')->where('status', 'DRAFT')->count());
        $this->assertSame(0, DB::table('gov_profile_assignments')->count());
    }

    public function test_takeover_keeps_creator_and_drafter_and_notifies_previous_manager(): void
    {
        $this->identity();
        Schema::create('gov_document_notices', function (Blueprint $t) {
            $t->id(); $t->uuid('document_id'); $t->integer('user_id'); $t->integer('actor_id');
            $t->string('event_key'); $t->timestamp('created_at');
        });
        $document = Document::create(['document_number' => 'GR-TAKEOVER', 'type' => 'receipt', 'status' => 'DRAFT',
            'location_id' => 10, 'company_id' => 20, 'created_by' => 2, 'drafted_by' => 2, 'managed_by' => 2]);
        $request = \Illuminate\Http\Request::create('/takeover', 'POST', ['reason' => 'Cover the previous storekeeper']);
        $request->setLaravelSession(app('session')->driver());
        app(\GovStore\StoreOperations\Http\Controllers\DocumentWorkspaceController::class)->takeover($request, 'receipt', $document->id);
        $document->refresh();
        $this->assertSame(2, (int) $document->created_by);
        $this->assertSame(2, (int) $document->drafted_by);
        $this->assertSame(1, (int) $document->managed_by);
        $this->assertSame(2, (int) DB::table('gov_document_notices')->value('user_id'));
        $this->assertSame(1, (int) DB::table('gov_document_notices')->value('actor_id'));
        $this->assertStringContainsString('Cover the previous storekeeper', $document->timelines()->value('notes'));
    }

    public function test_purchase_requires_live_supplier_before_materialization(): void
    {
        Schema::create('suppliers', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamp('deleted_at')->nullable(); });
        $receipt = Document::create(['document_number' => 'GR-SUPPLIER', 'type' => 'receipt', 'status' => 'DRAFT',
            'location_id' => 10, 'company_id' => 20, 'created_by' => 1, 'purchase_type' => 'Purchase']);
        $receipt->items()->create(['product_type' => 'consumable', 'product_id' => 1, 'quantity' => 1]);
        foreach ([null, 99, 7] as $supplier) {
            if ($supplier === 7) DB::table('suppliers')->insert(['id' => 7, 'name' => 'Deleted supplier', 'deleted_at' => now()]);
            $receipt->update(['supplier_id' => $supplier]);
            try { app(PostingPipelineManager::class)->materialize($receipt, 1); $this->fail('Purchase supplier must exist and be active.'); }
            catch (ValidationException $e) { $this->assertArrayHasKey('supplier_id', $e->errors()); }
            $this->assertSame('DRAFT', $receipt->fresh()->status);
            $this->assertSame(0, InventoryMovement::count());
        }
    }

    public function test_foreign_zero_quantity_line_cannot_bypass_office_check(): void
    {
        DB::table('consumables')->insert(['id' => 2, 'name' => 'Foreign', 'qty' => 20, 'location_id' => 11, 'company_id' => 20]);
        try { app(\GovStore\StoreOperations\Services\DocumentLineItemManager::class)->processLines([['type' => 'consumable', 'id' => 2, 'qty' => 0]]); $this->fail('Foreign IDs require checks even for zero quantity.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e) { $this->assertSame(404, $e->getStatusCode()); }
        $this->assertSame(0, Document::count());
    }

    public function test_ledger_rejects_mismatched_or_missing_stock_company(): void
    {
        $this->opened(10, 1, 20);
        $document = Document::create(['document_number' => 'GR-COMPANY', 'type' => 'receipt', 'status' => 'DRAFT',
            'company_id' => 20, 'location_id' => 10, 'created_by' => 1]);
        foreach ([21, null] as $company) {
            DB::table('consumables')->where('id', 1)->update(['company_id' => $company]);
            try { app(LedgerPostingService::class)->postMovement('consumable', 1, 'IN', 1, $document, 20, 10, 1); $this->fail('Unverified stock company must not post.'); }
            catch (\Exception $error) { $this->assertStringContainsString('posting company', $error->getMessage()); }
            $this->assertSame(1, InventoryMovement::count());
        }
    }

    public function test_office_rule_preview_uses_selected_context_without_replacing_shared_context(): void
    {
        $this->identity();
        Schema::create('gov_profiles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('status'); $t->timestamps(); });
        Schema::create('gov_profile_capabilities', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('profile_id'); $t->string('capability_code'); $t->string('behavior'); $t->json('config_payload')->nullable(); });
        Schema::create('gov_profile_assignments', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('profile_id'); $t->string('target_type'); $t->integer('target_id'); $t->string('scope_level'); $t->integer('scope_id')->nullable(); $t->timestamp('effective_from'); $t->timestamp('effective_to')->nullable(); });
        foreach ([1 => 'ENFORCE', 2 => 'DISABLE'] as $id => $behavior) {
            DB::table('gov_profiles')->insert(['id' => $id, 'name' => 'Preview '.$id, 'status' => 'PUBLISHED']);
            DB::table('gov_profile_capabilities')->insert(['profile_id' => $id, 'capability_code' => 'require_warranty', 'behavior' => $behavior]);
            DB::table('gov_profile_assignments')->insert(['profile_id' => $id, 'target_type' => $id === 1 ? 'Tenant' : 'Location',
                'target_id' => $id === 1 ? 20 : 11, 'scope_level' => $id === 1 ? 'COMPANY' : 'LOCATION', 'scope_id' => $id === 1 ? 20 : 11, 'effective_from' => now()->subDay()]);
        }
        \GovStore\StoreOperations\Services\ProfileCompilerService::clearResolvedCache();
        $shared = app(TenantContext::class);
        $preview = clone $shared; $preview->locationId = 11;
        $this->assertFalse((new \GovStore\StoreOperations\Services\ProfileCompilerService($preview))->compileContext()['require_warranty']['enforced']);
        $this->assertTrue((new \GovStore\StoreOperations\Services\ProfileCompilerService($shared))->compileContext()['require_warranty']['enforced']);
        $this->assertSame($shared, app(TenantContext::class)); $this->assertSame(10, $shared->locationId);
    }

    public function test_native_kardex_composer_requires_office_company_and_document_authority(): void
    {
        $this->identity();
        $composer = new \GovStore\StoreOperations\UI\NativeKardexComposer;
        $item = Consumable::findOrFail(1);
        $view = view('consumables/view', ['consumable' => $item]); $composer->compose($view);
        $this->assertCount(1, $view->getData()['govStoreKardexTabs']);
        $item->company_id = 21;
        $view = view('consumables/view', ['consumable' => $item]); $composer->compose($view);
        $this->assertSame([], $view->getData()['govStoreKardexTabs']);
        $item->company_id = 20;
        DB::table('gov_office_responsibilities')->delete();
        $view = view('consumables/view', ['consumable' => $item]); $composer->compose($view);
        $this->assertSame([], $view->getData()['govStoreKardexTabs']);
    }
}
