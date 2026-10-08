<?php

namespace Tests\Feature\GovStore;

use App\Models\Asset;
use App\Models\Setting;
use App\Models\User;
use GovStore\Tracking\Events\InventoryMaterializedAgainstProgramme;
use GovStore\Tracking\Http\Controllers\Api\TrackingEvaluationController;
use GovStore\Tracking\Http\Controllers\TrackingCodeController;
use GovStore\Tracking\Http\Controllers\TrackingDocumentController;
use GovStore\Tracking\Listeners\AssociateInventoryToProgramme;
use GovStore\Tracking\Models\Initiative;
use GovStore\Tracking\Models\TrackingAssociation;
use GovStore\Tracking\Models\TrackingCode;
use GovStore\Tracking\Models\TrackingDocument;
use GovStore\Tracking\Models\TrackingFactDelivery;
use GovStore\Tracking\Models\TrackingProjectionCache;
use GovStore\Tracking\Services\ProgrammeVerifier;
use GovStore\Tracking\Services\ScopeValidatorService;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\CustomFieldProvisioner;
use GovStore\StoreOperations\Services\PostingPipelineManager;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Inherits the isolated SQLite-only Store Operations fixture, never the live DB. */
class TrackingWorkflowTest extends StoreOperationsWorkflowTest
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['govstore-access.mode' => 'enforce']);
        foreach (['companies', 'manufacturers', 'suppliers'] as $name) {
            Schema::create($name, function (Blueprint $t) { $t->increments('id'); $t->string('name')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        }
        Schema::create('users', function (Blueprint $t) { $t->increments('id'); $t->string('first_name')->nullable(); $t->integer('location_id')->nullable(); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('locations', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('company_id'); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('categories', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('models', function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->integer('category_id'); $t->integer('manufacturer_id')->nullable(); $t->integer('company_id')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps(); });
        Schema::create('status_labels', function (Blueprint $t) { $t->increments('id'); $t->boolean('deployable'); $t->boolean('archived'); $t->timestamp('deleted_at')->nullable(); });
        Schema::create('assets', function (Blueprint $t) {
            $t->increments('id'); $t->integer('model_id'); $t->integer('status_id'); $t->integer('location_id'); $t->integer('company_id');
            $t->integer('assigned_to')->nullable(); $t->string('asset_tag'); $t->string('serial')->nullable();
            $t->integer('supplier_id')->nullable(); $t->decimal('purchase_cost')->nullable(); $t->timestamp('deleted_at')->nullable(); $t->timestamps();
        });
        Schema::create('gov_asset_registrations', function (Blueprint $t) { $t->increments('id'); $t->uuid('intake_item_id'); $t->integer('asset_id'); $t->string('asset_tag'); $t->string('serial_number')->nullable(); $t->timestamp('created_at'); });
        Schema::create('gov_geo_areas', function (Blueprint $t) { $t->unsignedInteger('GeoAreaId')->primary(); $t->string('hid'); });
        Schema::create('gov_tenant_scope_mappings', function (Blueprint $t) {
            $t->increments('id'); $t->string('reference_type'); $t->integer('reference_id'); $t->string('scope_type'); $t->integer('scope_id'); $t->boolean('is_active');
        });
        foreach (['gov_company_admins' => ['user_id', 'company_id'], 'gov_ict_jurisdictions' => ['user_id'],
            'gov_location_profiles' => ['location_id', 'office_admin_id', 'geo_area_id'], 'gov_office_responsibilities' => ['user_id', 'location_id', 'role_slug']] as $name => $columns) {
            Schema::create($name, function (Blueprint $t) use ($columns) { $t->increments('id'); foreach ($columns as $c) $t->string($c)->nullable(); $t->timestamps(); });
        }
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        foreach (glob(base_path('packages/gov-store/tracking/src/database/migrations/*create*php')) as $path) (require $path)->up();
        (require base_path('packages/gov-store/tracking/src/database/migrations/2026_10_08_000004_add_tracking_documents_and_deliveries.php'))->up();
        DB::table('companies')->insert([['id' => 20], ['id' => 21]]);
        DB::table('users')->insert(['id' => 1, 'first_name' => 'Tracking tester']);
        DB::table('locations')->insert([['id' => 10, 'name' => 'Office A', 'company_id' => 20], ['id' => 11, 'name' => 'Office B', 'company_id' => 21]]);
        DB::table('categories')->insert(['id' => 1, 'name' => 'Equipment']);
        DB::table('models')->insert(['id' => 1, 'name' => 'Laptop', 'category_id' => 1]);
        DB::table('status_labels')->insert(['id' => 1, 'deployable' => 1, 'archived' => 0]);
        DB::table('consumables')->update(['category_id' => 1]);
        $context = app(TenantContext::class); $context->isActive = true;
        $context->allowedInventoryLocationIds = [10]; $context->allowedInventoryCompanyIds = [20];
        Setting::$_cache = new Setting(['full_multiple_companies_support' => '0']);
    }

    protected function tearDown(): void
    {
        Setting::$_cache = null;
        parent::tearDown();
    }

    private function actor(string $role = 'storekeeper'): User
    {
        $user = Mockery::mock(User::class)->makePartial(); $user->id = 1; $user->location_id = 10;
        $user->shouldReceive('isSuperUser')->andReturn($role === 'superuser');
        $user->shouldReceive('hasAccess')->andReturn($role === 'native_admin');
        auth()->setUser($user);
        if ($role === 'storekeeper') DB::table('gov_office_responsibilities')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'storekeeper']);
        return $user;
    }

    private function task(int $company = 20): TrackingCode
    {
        $initiative = Initiative::withoutGlobalScopes()->create(['title' => 'Tracking test', 'owner_company_id' => $company, 'primary_funding' => 'ADP', 'status' => 'Active']);
        $fund = DB::table('gov_funding_types')->insertGetId(['name' => 'Test fund', 'primary_type' => 'ADP']);
        return TrackingCode::create(['initiative_id' => $initiative->id, 'funding_type_id' => $fund,
            'tracking_code' => 'TR-'.$initiative->id, 'task_title' => 'Delivery', 'specificity_level' => '1_BLANKET', 'fiscal_year' => '2026-27', 'status' => 'ACTIVE']);
    }

    private function delivery(TrackingCode $task, string $id = 'receipt-line-a'): InventoryMaterializedAgainstProgramme
    {
        return new InventoryMaterializedAgainstProgramme($task->tracking_code, 1, 0, 0, 10, 3, 90, 0, 1, 'GRN-TEST', [], null, $id);
    }

    private function checkStatus(int $status, callable $call): void
    {
        try { $call(); $this->fail('Expected HTTP '.$status); }
        catch (HttpExceptionInterface $e) { $this->assertSame($status, $e->getStatusCode()); }
    }

    public function test_projection_refreshes_old_and_new_initiatives_after_association_changes_and_delete(): void
    {
        Bus::fake(); $a = $this->task(); $b = $this->task();
        DB::table('gov_tracking_projection_caches')->insert(['tracking_reference_id' => $a->initiative_id, 'received' => 99]);
        $association = TrackingAssociation::create(['tracking_code_id' => $a->id, 'category_id' => 1, 'location_id' => 10,
            'quantity' => 3, 'associatable_type' => 'test', 'associatable_id' => 1, 'status' => 'ACTIVE']);
        $this->assertNull(TrackingProjectionCache::where('tracking_reference_id', $a->initiative_id)->first());
        Bus::assertDispatched(\GovStore\Tracking\Jobs\RebuildTrackingProjectionJob::class);
        $association->update(['tracking_code_id' => $b->id]);
        (new \GovStore\Tracking\Jobs\RebuildTrackingProjectionJob($a->initiative_id))->handle(app(\GovStore\Tracking\Repositories\EloquentTrackingProjectionRepository::class));
        (new \GovStore\Tracking\Jobs\RebuildTrackingProjectionJob($b->initiative_id))->handle(app(\GovStore\Tracking\Repositories\EloquentTrackingProjectionRepository::class));
        $this->assertSame(0, (int) TrackingProjectionCache::where('tracking_reference_id', $a->initiative_id)->value('received'));
        $this->assertSame(3, (int) TrackingProjectionCache::where('tracking_reference_id', $b->initiative_id)->value('received'));
        $association->delete();
        $this->assertNull(TrackingProjectionCache::where('tracking_reference_id', $b->initiative_id)->first());
    }

    public function test_projection_is_dispatched_only_after_commit_and_not_after_rollback(): void
    {
        Bus::fake(); $task = $this->task(); Bus::fake();
        DB::beginTransaction();
        TrackingAssociation::create(['tracking_code_id' => $task->id, 'category_id' => 1, 'location_id' => 10, 'quantity' => 1,
            'associatable_type' => 'test', 'associatable_id' => 1, 'status' => 'ACTIVE']);
        Bus::assertNothingDispatched(); DB::rollBack(); Bus::assertNothingDispatched();
        DB::beginTransaction(); \GovStore\Tracking\Services\ProjectionRefresh::initiative($task->initiative_id);
        Bus::assertNothingDispatched(); DB::commit(); Bus::assertDispatched(\GovStore\Tracking\Jobs\RebuildTrackingProjectionJob::class);
    }

    public function test_receipt_delivery_replay_updates_facts_associations_and_timeline_once(): void
    {
        $task = $this->task(); $listener = app(AssociateInventoryToProgramme::class); $event = $this->delivery($task);
        $listener->handle($event); $listener->handle($event);
        $this->assertSame(3, (int) TrackingAssociation::sum('quantity'));
        $this->assertSame(3, (int) TrackingFactDelivery::sum('received_qty'));
        $this->assertSame(1, DB::table('gov_tracking_timeline')->count());
        $this->assertSame(3, (int) TrackingProjectionCache::where('tracking_reference_id', $task->initiative_id)->value('received'));
        $listener->handle($this->delivery($task, 'receipt-line-b'));
        $this->assertSame(6, (int) TrackingAssociation::sum('quantity'));
        $this->assertSame(6, (int) TrackingFactDelivery::sum('received_qty'));
        $this->assertSame(1, TrackingFactDelivery::count());
    }

    public function test_delivery_and_projection_rollback_together(): void
    {
        $task = $this->task(); DB::beginTransaction();
        app(AssociateInventoryToProgramme::class)->handle($this->delivery($task)); DB::rollBack();
        $this->assertSame(0, TrackingAssociation::count()); $this->assertSame(0, TrackingFactDelivery::count());
        $this->assertSame(0, DB::table('gov_tracking_deliveries')->count());
        $this->assertSame(0, DB::table('gov_tracking_timeline')->count());
    }

    public function test_execution_checks_actor_working_office_and_live_permissions_in_shadow(): void
    {
        $task = $this->task(); $this->actor(); $verifier = app(ProgrammeVerifier::class);
        $this->assertSame($task->id, $verifier->resolve($task->tracking_code, 10)->id);
        config(['govstore-access.mode' => 'shadow']);
        $this->checkStatus(403, fn () => $verifier->resolve($task->tracking_code, 11));
        app(TenantContext::class)->isGlobal = true;
        $this->checkStatus(403, fn () => $verifier->resolve($task->tracking_code, 10));
    }

    public function test_native_admin_is_not_programme_admin_and_cannot_evaluate_without_responsibility(): void
    {
        $task = $this->task(); $this->actor('native_admin');
        $this->assertSame(0, Initiative::count());
        $this->checkStatus(403, fn () => app(ProgrammeVerifier::class)->resolve($task->tracking_code, 10));
    }

    public function test_scope_requires_geography_and_participating_office(): void
    {
        $task = $this->task();
        DB::table('gov_geo_areas')->insert([['GeoAreaId' => 1, 'hid' => '/36/'], ['GeoAreaId' => 2, 'hid' => '/36/28/'], ['GeoAreaId' => 3, 'hid' => '/40/']]);
        DB::table('gov_location_profiles')->insert(['location_id' => 10, 'geo_area_id' => 2]);
        $task->scopes()->create(['dimension' => 'GEOGRAPHY', 'target_type' => 'GeoArea', 'target_id' => 1]);
        $task->scopes()->create(['dimension' => 'PARTICIPANTS', 'target_type' => 'SpecificLocations', 'target_id' => 11]);
        $scope = app(ScopeValidatorService::class);
        $task->setRelation('initiative', Initiative::withoutGlobalScopes()->find($task->initiative_id));
        $this->assertFalse($scope->validateExecutionScope($task, 10)['is_valid']);
        $task->scopes()->where('dimension', 'PARTICIPANTS')->update(['target_id' => 10]); $task->unsetRelation('scopes');
        $this->assertTrue($scope->validateExecutionScope($task, 10)['is_valid']);
        DB::table('gov_location_profiles')->update(['geo_area_id' => 3]);
        $this->assertFalse($scope->validateExecutionScope($task, 10)['is_valid']);
    }

    public function test_evaluation_uses_association_totals_and_rejects_foreign_office(): void
    {
        $task = $this->task(); $this->actor(); $task->update(['specificity_level' => '3_MATRIX']);
        $target = $task->targets()->create(['category_id' => 1, 'planned_qty' => 4]);
        $target->allocations()->create(['location_id' => 10, 'allocated_qty' => 4]);
        app(AssociateInventoryToProgramme::class)->handle($this->delivery($task));
        $controller = app(TrackingEvaluationController::class);
        $request = Request::create('/', 'GET', ['code' => $task->tracking_code, 'location_id' => 10, 'category_id' => 1, 'qty' => 2]);
        $this->assertTrue($controller->evaluate($request)->getData(true)['target_status']['is_exceeded']);
        $request->merge(['location_id' => 11]); $this->checkStatus(403, fn () => $controller->evaluate($request));
    }

    public function test_nested_foreign_task_is_rejected_before_mutation(): void
    {
        Bus::fake(); $a = $this->task(); $b = $this->task(21); $this->actor('superuser');
        $this->checkStatus(404, fn () => app(TrackingCodeController::class)->archive(Initiative::find($a->initiative_id), $b));
        $this->assertSame('ACTIVE', $b->fresh()->status);
    }

    public function test_private_documents_check_task_ownership_role_state_and_storage(): void
    {
        Storage::fake('local'); Bus::fake(); $task = $this->task(); $task->update(['status' => 'DRAFT']); $this->actor('superuser');
        $initiative = Initiative::find($task->initiative_id); $controller = app(TrackingDocumentController::class);
        $request = Request::create('/', 'POST'); $request->files->set('document', UploadedFile::fake()->create('evidence.pdf', 1, 'application/pdf'));
        $controller->store($request, $initiative, $task); $doc = TrackingDocument::firstOrFail();
        Storage::disk('local')->assertExists($doc->file_path);
        $this->assertSame(200, $controller->download($initiative, $task, $doc)->getStatusCode());
        $other = $this->task();
        $this->checkStatus(404, fn () => $controller->download($initiative, $other, $doc));
        $this->actor('employee'); $this->checkStatus(403, fn () => $controller->download($initiative, $task, $doc));
        $this->actor('superuser'); $task->update(['status' => 'ACTIVE']);
        $this->checkStatus(409, fn () => $controller->destroy($initiative, $task, $doc));
        $task->update(['status' => 'DRAFT']); $controller->destroy($initiative, $task, $doc);
        Storage::disk('local')->assertMissing($doc->file_path); $this->assertSame(0, TrackingDocument::count());
    }

    public function test_translations_and_effective_tracking_handlers_are_registered(): void
    {
        $en = require base_path('packages/gov-store/tracking/src/resources/lang/en-US/general.php');
        $bn = require base_path('packages/gov-store/tracking/src/resources/lang/bn-BD/general.php');
        $this->assertSame(array_keys(\Illuminate\Support\Arr::dot($en)), array_keys(\Illuminate\Support\Arr::dot($bn)));
        foreach (\Illuminate\Support\Facades\Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'gov-store/admin/tracking') && ! str_starts_with($route->uri(), 'gov-store/api/tracking')) continue;
            [$class, $method] = explode('@', $route->getAction('controller'));
            $this->assertTrue(method_exists($class, $method), $route->uri());
            $this->assertContains(\GovStore\Tracking\Http\Middleware\SafeTrackingResponse::class, $route->gatherMiddleware());
        }
    }

    public function test_matrix_save_rejects_negative_foreign_and_duplicate_dimensions(): void
    {
        Bus::fake(); $task = $this->task(); $task->update(['status' => 'DRAFT', 'specificity_level' => '3_MATRIX']);
        $this->actor('superuser'); $initiative = Initiative::find($task->initiative_id);
        $controller = app(TrackingCodeController::class);
        $base = ['task_title' => 'Updated matrix', 'fiscal_year' => '2026-27', 'funding_type_id' => $task->funding_type_id,
            'geo_override' => 'Inherit', 'participant_override' => 'Inherit',
            'matrix_categories' => [1], 'matrix_locations' => [10], 'matrix_values' => [[1 => 4]]];
        foreach ([['matrix_values' => [[1 => -1]]], ['matrix_locations' => [11]], ['matrix_categories' => [1, 1]]] as $invalid) {
            try { $controller->update(Request::create('/', 'PUT', array_replace($base, $invalid)), $initiative, $task); $this->fail('Invalid matrix was accepted'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
            $this->assertSame('Delivery', $task->fresh()->task_title);
            $this->assertSame(0, $task->targets()->count());
        }
        $controller->update(Request::create('/', 'PUT', $base), $initiative, $task);
        $this->assertSame(4, (int) $task->targets()->first()->planned_qty);
        $this->assertSame(10, (int) $task->targets()->first()->allocations()->first()->location_id);
    }

    private function serializedReceipt(TrackingCode $task): Document
    {
        $this->actor();
        // Physical custom-field provisioning and native audit logging are independent adapters.
        app()->instance(CustomFieldProvisioner::class, Mockery::mock(CustomFieldProvisioner::class)
            ->shouldReceive('getDbColumn')->andReturnNull()->getMock());
        app()->instance(\App\Observers\AssetObserver::class, Mockery::mock(\App\Observers\AssetObserver::class)->makePartial()
            ->shouldReceive('created')->andReturnNull()->getMock());
        DB::table('gov_store_ledger_openings')->insert(['location_id' => 10, 'document_id' => 'opening-fixture', 'opened_at' => now(), 'opened_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $receipt = Document::create(['document_number' => 'GRN-SERIAL-TEST', 'type' => 'receipt', 'status' => 'DRAFT',
            'company_id' => 20, 'location_id' => 10, 'created_by' => 1,
            'compiled_profile_snapshot' => ['items' => ['asset_model_1' => ['create_assets' => ['status_id' => 1]]]]]);
        $item = $receipt->items()->create(['product_type' => 'asset_model', 'product_id' => 1, 'quantity' => 2, 'unit_cost' => 30]);
        foreach ([0, 1] as $index) {
            $item->metadata()->create(['field_key' => 'asset_tag', 'value' => 'TR-TEST-'.$index, 'row_index' => $index]);
            $item->metadata()->create(['field_key' => 'serial_number', 'value' => 'TR-SERIAL-'.$index, 'row_index' => $index]);
        }
        $receipt->references()->create(['reference_type' => 'Special Allocation', 'reference_number' => $task->tracking_code]);
        return $receipt;
    }

    public function test_actual_serialized_receipt_posts_assets_and_programme_exactly_once(): void
    {
        $task = $this->task(); $receipt = $this->serializedReceipt($task);
        app(PostingPipelineManager::class)->materialize($receipt, 1);
        $this->assertSame('POSTED', $receipt->fresh()->status);
        $this->assertSame(2, Asset::count()); $this->assertSame(2, DB::table('gov_asset_registrations')->count());
        $this->assertSame(2, TrackingAssociation::count()); $this->assertSame(2, (int) TrackingFactDelivery::sum('received_qty'));
        $this->assertSame(2, (int) TrackingProjectionCache::where('tracking_reference_id', $task->initiative_id)->value('received'));
        try { app(PostingPipelineManager::class)->materialize($receipt, 1); $this->fail('Posting replay must fail'); }
        catch (\Exception $e) { $this->assertStringContainsString('already been posted', $e->getMessage()); }
        $this->assertSame(1, DB::table('gov_tracking_deliveries')->count());
        $this->assertSame(2, (int) TrackingFactDelivery::sum('received_qty'));
    }

    public function test_safe_response_rolls_back_rendered_failure_and_preserves_meaningful_status(): void
    {
        Bus::fake(); $task = $this->task();
        \Illuminate\Support\Facades\Log::spy();
        $request = Request::create('/gov-store/admin/tracking/initiatives', 'POST');
        $request->headers->set('Accept', 'application/json');
        $session = app('session')->driver(); $session->start(); $session->flush();
        $request->setLaravelSession($session);
        $middleware = app(\GovStore\Tracking\Http\Middleware\SafeTrackingResponse::class);
        $response = $middleware->handle($request, function () use ($task) {
            DB::table('gov_tracking_deliveries')->insert(['delivery_key' => 'rendered-failure', 'tracking_code_id' => $task->id, 'created_at' => now()]);
            return response()->json(['message' => 'Denied'], 403);
        });
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, DB::table('gov_tracking_deliveries')->count());
        $response = $middleware->handle($request, fn () => throw new \RuntimeException('SQL secret path'));
        $this->assertSame(500, $response->getStatusCode());
        $this->assertArrayHasKey('reference_id', $response->getData(true));
        $this->assertStringNotContainsString('SQL secret path', $response->getContent());
        $session->put('_flash.old', ['error']); $session->put('error', 'Previous failure');
        $response = $middleware->handle($request, function () use ($task) {
            DB::table('gov_tracking_deliveries')->insert(['delivery_key' => 'success', 'tracking_code_id' => $task->id, 'created_at' => now()]);
            return response()->json(['ok' => true]);
        });
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(1, DB::table('gov_tracking_deliveries')->count());
    }

    public function test_serialized_receipt_listener_failure_rolls_back_native_assets_and_ledger(): void
    {
        $task = $this->task(); $receipt = $this->serializedReceipt($task);
        $task->update(['status' => 'ARCHIVED']);
        try { app(PostingPipelineManager::class)->materialize($receipt, 1); $this->fail('Inactive code must block posting'); }
        catch (\InvalidArgumentException $e) { $this->assertStringContainsString('active execution scope', $e->getMessage()); }
        $this->assertSame('DRAFT', $receipt->fresh()->status); $this->assertSame(0, Asset::count());
        $this->assertSame(0, DB::table('gov_inventory_movements')->count());
        $this->assertSame(0, TrackingAssociation::count()); $this->assertSame(0, TrackingFactDelivery::count());
        $this->assertSame(0, DB::table('gov_asset_registrations')->count());
    }
}
