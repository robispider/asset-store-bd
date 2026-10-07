<?php

namespace Tests\Feature\GovStore;

use App\Events\CheckoutableCheckedOut;
use App\Models\Accessory;
use App\Models\AssetModel;
use App\Models\Consumable;
use App\Models\User;
use GovStore\CustomRequests\Http\Controllers\GovApprovalController;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Rules\NoPendingRequestsRule;
use GovStore\CustomRequests\Services\ApprovalRouting;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\BasketService;
use GovStore\CustomRequests\Services\CatalogService;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\CustomRequests\Services\PolicyService;
use GovStore\CustomRequests\Services\RequesterService;
use GovStore\CustomRequests\Services\RequestInventory;
use GovStore\CustomRequests\Services\RequestReturnService;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\GoodsReceiptService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Only uses SQLite memory. Baseline and forward migrations exercise the actual package schema. */
class CustomRequestsWorkflowTest extends TestCase
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
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:', 'cache.default' => 'array', 'govstore-access.mode' => 'enforce']);
        DB::purge('sqlite');
        DB::statement('PRAGMA foreign_keys = ON');
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id');
            $t->string('first_name');
            $t->string('last_name')->nullable();
            $t->integer('location_id')->nullable();
            $t->integer('company_id')->nullable();
            $t->text('permissions')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('locations', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('permission_groups', function (Blueprint $t) {
            $t->increments('id');
            $t->text('permissions')->nullable();
        });
        Schema::create('users_groups', function (Blueprint $t) {
            $t->integer('user_id');
            $t->integer('group_id');
        });
        foreach (['gov_company_admins' => ['user_id', 'company_id'], 'gov_ict_jurisdictions' => ['user_id'],
            'gov_location_profiles' => ['location_id', 'office_admin_id'], 'gov_office_responsibilities' => ['user_id', 'location_id', 'role_slug']] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($columns) {
                $t->increments('id');
                foreach ($columns as $column) {
                    $t->string($column)->nullable();
                } $t->timestamps();
            });
        }
        Schema::create('gov_office_memberships', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('user_id');
            $t->integer('location_id');
            $t->string('status');
        });
        (require base_path('packages/gov-store/tenant-scope/src/database/migrations/2026_10_04_000001_create_gov_access_tables.php'))->up();
        foreach (['categories', 'models', 'components', 'licenses', 'accessories', 'consumables'] as $table) {
            Schema::create($table, function (Blueprint $t) {
                $t->increments('id');
                $t->string('name');
                $t->integer('category_id')->nullable();
                $t->integer('qty')->default(20);
                $t->integer('location_id')->default(10);
                $t->integer('company_id')->nullable();
                $t->decimal('purchase_cost', 12, 2)->nullable();
                $t->string('image')->nullable();
                $t->timestamp('deleted_at')->nullable();
                $t->timestamps();
            });
        }
        foreach (['consumables_users' => 'consumable_id', 'accessories_checkout' => 'accessory_id'] as $table => $column) {
            Schema::create($table, function (Blueprint $t) use ($column) {
                $t->increments('id');
                $t->integer($column);
                $t->integer('assigned_to')->nullable();
                $t->string('assigned_type')->nullable();
                $t->text('note')->nullable();
            });
        }
        Schema::create('status_labels', function (Blueprint $t) {
            $t->increments('id');
            $t->boolean('deployable');
            $t->boolean('archived');
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('assets', function (Blueprint $t) {
            $t->increments('id');
            $t->integer('company_id')->nullable();
            $t->integer('model_id');
            $t->integer('status_id');
            $t->integer('location_id');
            $t->boolean('requestable')->default(1);
            $t->integer('assigned_to')->nullable();
            $t->string('assigned_type')->nullable();
            $t->string('asset_tag');
            $t->string('name')->nullable();
            $t->timestamp('last_checkout')->nullable();
            $t->integer('checkout_counter')->default(0);
            $t->timestamp('deleted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('action_logs', function (Blueprint $t) {
            $t->increments('id');
            foreach (['item_id', 'target_id', 'created_by', 'location_id', 'company_id', 'quantity'] as $c) {
                $t->integer($c)->nullable();
            }
            foreach (['item_type', 'target_type', 'action_type', 'remote_ip', 'action_source', 'user_agent'] as $c) {
                $t->string($c)->nullable();
            }
            $t->text('note')->nullable();
            $t->text('log_meta')->nullable();
            $t->timestamp('action_date')->nullable();
            $t->timestamps();
        });
        Schema::create('gov_documents', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('document_number')->nullable();
            $t->string('type')->nullable();
            $t->string('status')->default('DRAFT');
            $t->text('compiled_profile_snapshot')->nullable();
            $t->integer('location_id')->default(10);
            $t->integer('company_id')->default(20);
            $t->integer('created_by')->nullable();
            $t->integer('drafted_by')->nullable();
            $t->integer('posted_by')->nullable();
            $t->integer('managed_by')->nullable();
            $t->integer('issued_to_user_id')->nullable();
            $t->string('issue_department')->nullable();
            $t->timestamp('posted_at')->nullable();
            $t->timestamps();
        });
        Schema::create('gov_document_items', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('document_id');
            $t->string('product_type');
            $t->integer('product_id');
            $t->integer('quantity');
            $t->decimal('unit_cost', 15, 2)->nullable();
            $t->timestamps();
        });
        Schema::create('gov_document_item_meta', function (Blueprint $t) {
            $t->increments('id');
            $t->uuid('document_item_id');
            $t->string('field_key');
            $t->text('value');
            $t->integer('row_index')->default(0);
        });
        Schema::create('gov_document_references', function (Blueprint $t) {
            $t->increments('id');
            $t->string('document_type');
            $t->uuid('document_id');
            $t->string('reference_type');
            $t->string('reference_number');
            $t->date('reference_date')->nullable();
            $t->timestamps();
        });
        Schema::create('gov_document_timelines', function (Blueprint $t) {
            $t->increments('id');
            $t->string('document_type');
            $t->uuid('document_id');
            $t->string('state');
            $t->integer('user_id');
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        Schema::create('gov_store_document_sequences', function (Blueprint $t) {
            $t->string('prefix', 8);
            $t->integer('sequence_year');
            $t->integer('last_number')->default(0);
            $t->primary(['prefix', 'sequence_year']);
        });
        Schema::create('gov_store_ledger_openings', function (Blueprint $t) {
            $t->integer('location_id')->primary();
            $t->uuid('document_id')->unique();
            $t->timestamp('opened_at');
            $t->integer('opened_by');
            $t->timestamps();
        });
        Schema::create('gov_goods_issues', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('issue_no')->unique();
            $t->string('issue_type');
            $t->integer('issued_to_id');
            $t->string('reference_type');
            $t->integer('reference_id');
            $t->string('status');
            $t->integer('company_id');
            $t->integer('location_id');
            $t->integer('created_by');
            $t->timestamps();
        });
        Schema::create('gov_goods_issue_items', function (Blueprint $t) {
            $t->increments('id');
            $t->uuid('goods_issue_id');
            $t->string('stockable_type');
            $t->integer('stockable_id');
            $t->integer('quantity');
        });
        Schema::create('gov_inventory_movements', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('stockable_type');
            $t->integer('stockable_id');
            $t->string('movement_type');
            $t->integer('quantity');
            $t->integer('balance_after');
            $t->string('document_type');
            $t->uuid('document_id');
            $t->integer('company_id');
            $t->integer('location_id');
            $t->integer('created_by');
            $t->text('notes')->nullable();
            $t->timestamp('created_at')->nullable();
        });
        foreach (['2024_01_02_000000_create_service_requests_tables.php' => 'CreateServiceRequestsTables',
            '2024_01_03_000000_create_gov_approval_policy_tables.php' => 'CreateGovApprovalPolicyTables'] as $file => $class) {
            require_once base_path('packages/gov-store/custom-requests/src/database/migrations/'.$file);
            (new $class)->up();
        }
        foreach (['2024_01_04_000000_create_draft_basket_tables.php', '2026_10_04_000001_harden_custom_request_workflow.php', '2026_10_04_000002_add_request_thresholds_and_notices.php', '2026_10_04_000003_add_request_returns.php'] as $file) {
            (require base_path('packages/gov-store/custom-requests/src/database/migrations/'.$file))->up();
        }
        $context = new TenantContext;
        $context->locationId = 10;
        $context->companyId = 20;
        $context->allowedLocationIds = [10];
        app()->instance(TenantContext::class, $context);
        DB::table('locations')->insert([['id' => 10, 'name' => 'Working office'], ['id' => 11, 'name' => 'Other office']]);
        DB::table('users')->insert(['id' => 1, 'first_name' => 'Requester', 'location_id' => 11]);
        DB::table('gov_office_memberships')->insert(['user_id' => 1, 'location_id' => 10, 'status' => 'active']);
        DB::table('gov_store_ledger_openings')->insert([
            'location_id' => 10, 'document_id' => (string) Str::uuid(), 'opened_at' => now(), 'opened_by' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('categories')->insert(['id' => 1, 'name' => 'Supplies']);
        DB::table('consumables')->insert(['id' => 1, 'name' => 'Paper', 'category_id' => 1, 'purchase_cost' => 10]);
        DB::table('accessories')->insert(['id' => 1, 'name' => 'Keyboard', 'category_id' => 1, 'purchase_cost' => 10]);
    }

    private function actor(string $role, int $id = 2): User
    {
        DB::table('users')->insertOrIgnore(['id' => $id, 'first_name' => 'Actor '.$id, 'location_id' => 10]);
        $user = Mockery::mock(User::class)->makePartial();
        $user->id = $id;
        $user->location_id = 10;
        $user->shouldReceive('isSuperUser')->andReturn($role === 'superuser');
        $user->shouldReceive('hasAccess')->andReturn(false);
        auth()->setUser($user);
        DB::table('gov_office_responsibilities')->insert(['location_id' => 10, 'user_id' => $id, 'role_slug' => $role]);

        return $user;
    }

    private function request(array $attributes = [], array $types = ['consumable']): ServiceRequest
    {
        $request = ServiceRequest::create($attributes + ['office_id' => 10, 'requested_by' => 1, 'request_type' => 'other',
            'purpose' => 'Office supplies', 'justification' => 'Normal work', 'approval_status' => 'pending_primary',
            'fulfillment_status' => 'unstarted', 'resolved_policy' => 'PRIMARY_ONLY']);
        foreach ($types as $type) {
            $request->items()->create(['requested_type' => $type, 'requested_id' => 1, 'requested_qty' => 5,
                'approved_qty' => in_array($request->approval_status, ['approved', 'partially_approved']) ? 5 : 0,
                'line_approval_status' => in_array($request->approval_status, ['approved', 'partially_approved']) ? 'approved' : 'pending']);
        }

        return $request;
    }

    private function assertStatus(int $expected, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected denial '.$expected);
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($expected, $e->getStatusCode());
        }
    }

    private function decisions($request, int $qty = 5): array
    {
        return $request->items()->get()->mapWithKeys(fn ($line) => [$line->id => ['status' => 'approved', 'qty' => $qty]])->all();
    }

    public function test_basket_splits_policies_and_routes_by_working_office_without_delivery(): void
    {
        $service = app(BasketService::class);
        $service->addItem(1, 'consumable', 1, 2);
        $service->addItem(1, 'consumable', 1, 3);
        $service->addItem(1, 'accessory', 1);
        ApprovalPolicy::create(['target_type' => 'accessory', 'target_id' => 1, 'policy_name' => 'AUTO_APPROVE']);
        $requests = $service->submitBasket(1, ['request_type' => 'other', 'purpose' => 'Supplies', 'justification' => 'Work',
            'required_by_date' => now()->addDay()->toDateString(), 'cost_center' => 'ICT']);
        $this->assertCount(2, $requests);
        foreach ($requests as $request) {
            $this->assertSame(10, $request->office_id);
            $this->assertNull($request->delivery_location_id);
            $this->assertSame('ICT', $request->cost_center);
            $this->assertNotNull($request->required_by_date);
        }
        $this->assertSame(5, $requests[1]->items()->first()->requested_qty);
        $this->assertSame(0, DraftBasket::count());
    }

    public function test_basket_rejects_unsupported_types_foreign_stock_and_excessive_quantities(): void
    {
        $service = app(BasketService::class);
        foreach (['user', 'component', 'license'] as $type) {
            $this->assertStatus(422, fn () => $service->addItem(1, $type, 1));
        }
        DB::table('consumables')->where('id', 1)->update(['location_id' => 11]);
        $this->assertStatus(404, fn () => $service->addItem(1, 'consumable', 1));
        $this->expectException(ValidationException::class);
        $service->addItem(1, 'accessory', 1, 10001);
    }

    public function test_expired_basket_cannot_be_submitted_and_replaced_on_next_visit(): void
    {
        $basket = app(BasketService::class)->addItem(1, 'consumable', 1);
        $basket->update(['expires_at' => now()->subSecond()]);
        $new = app(BasketService::class)->getOrCreateDraftBasket(1);
        $this->assertNotSame($basket->id, $new->id);
        $this->assertCount(0, $new->items);
    }

    public function test_item_then_category_then_default_policy_for_every_type(): void
    {
        DB::table('models')->insert(['id' => 1, 'name' => 'Laptop', 'category_id' => 1]);
        DB::table('components')->insert(['id' => 1, 'name' => 'RAM', 'category_id' => 1]);
        DB::table('licenses')->insert(['id' => 1, 'name' => 'Software', 'category_id' => 1]);
        $policy = app(PolicyService::class);
        foreach (['asset_model', 'component', 'license', 'consumable', 'accessory'] as $type) {
            ApprovalPolicy::query()->delete();
            $this->assertSame('PRIMARY_ONLY', $policy->resolvePolicy($type, 1));
            ApprovalPolicy::create(['target_type' => 'category', 'target_id' => 1, 'policy_name' => 'PRIMARY_AND_FINAL']);
            $this->assertSame('PRIMARY_AND_FINAL', $policy->resolvePolicy($type, 1));
            ApprovalPolicy::create(['target_type' => $type, 'target_id' => 1, 'policy_name' => 'AUTO_APPROVE']);
            $this->assertSame('AUTO_APPROVE', $policy->resolvePolicy($type, 1));
        }
    }

    public function test_two_distinct_stage_approvers_and_primary_quantity_cap(): void
    {
        $request = $this->request(['resolved_policy' => 'PRIMARY_AND_FINAL']);
        $service = app(ApprovalService::class);
        $primary = $this->actor('primary_approver');
        $service->processDecision($request, $primary, $this->decisions($request, 10));
        $this->assertSame('pending_final', $request->fresh()->approval_status);
        $this->assertSame(5, $request->items()->first()->approved_qty);
        $this->actor('final_approver', 2);
        $this->assertStatus(403, fn () => $service->processDecision($request, $primary, $this->decisions($request)));
        $final = $this->actor('final_approver', 3);
        $result = $service->processDecision($request, $final, $this->decisions($request));
        $this->assertSame('approved', $result->approval_status);
        $this->assertSame(5, $result->items()->first()->reserved_qty);
        $this->assertStatus(409, fn () => $service->processDecision($request, $final, $this->decisions($request)));
    }

    public function test_self_approval_and_foreign_office_fail_in_shadow_and_stage_roles_apply_in_enforce(): void
    {
        $request = $this->request();
        $service = app(ApprovalService::class);
        $self = $this->actor('primary_approver', 1);
        config(['govstore-access.mode' => 'shadow']);
        $this->assertStatus(403, fn () => $service->processDecision($request, $self, $this->decisions($request)));
        config(['govstore-access.mode' => 'enforce']);
        $final = $this->actor('final_approver');
        $this->assertStatus(403, fn () => $service->processDecision($request, $final, $this->decisions($request)));
        config(['govstore-access.mode' => 'shadow']);
        $request->update(['office_id' => 11]);
        $this->assertStatus(404, fn () => $service->processDecision($request, $final, $this->decisions($request)));
        $this->assertSame('pending_primary', $request->fresh()->approval_status);
    }

    public function test_foreign_line_missing_decision_and_invalid_status_do_not_partially_commit(): void
    {
        $request = $this->request([], ['accessory', 'consumable']);
        $actor = $this->actor('primary_approver');
        $service = app(ApprovalService::class);
        $decisions = $this->decisions($request);
        $foreign = $decisions + [999 => ['status' => 'approved', 'qty' => 1]];
        $this->assertStatus(422, fn () => $service->processDecision($request, $actor, $foreign));
        $this->assertStatus(422, fn () => $service->processDecision($request, $actor, array_slice($decisions, 0, 1, true)));
        $this->assertSame(0, $request->items()->sum('approved_qty'));
        $this->assertSame(0, $request->events()->count());
        $this->expectException(ValidationException::class);
        $decisions[array_key_first($decisions)]['status'] = 'anything';
        $service->processDecision($request, $actor, $decisions);
    }

    public function test_stock_reservations_prevent_overapproval_and_roll_back_the_decision(): void
    {
        DB::table('consumables')->where('id', 1)->update(['qty' => 5]);
        $actor = $this->actor('primary_approver');
        $service = app(ApprovalService::class);
        $first = $this->request();
        $second = $this->request();
        $service->processDecision($first, $actor, $this->decisions($first));
        $this->assertStatus(409, fn () => $service->processDecision($second, $actor, $this->decisions($second)));
        $this->assertSame('pending_primary', $second->fresh()->approval_status);
        $this->assertSame(0, $second->items()->first()->approved_qty);
    }

    private function issuer(): FulfillmentService
    {
        $issuer = Mockery::mock(StockIssuingServiceInterface::class);
        $issuer->shouldReceive('issueSystemStock')->andReturnUsing(function ($payload) {
            $result = [];
            foreach ($payload as $line) {
                DB::table($line['type'] === 'consumable' ? 'consumables' : 'accessories')->where('id', $line['id'])->decrement('qty', $line['qty']);
                $result[$line['line_id']] = 'GI-TEST';
            }

            return $result;
        });

        return new FulfillmentService($issuer);
    }

    public function test_bulk_stock_is_changed_once_and_multisession_issue_completes(): void
    {
        $request = $this->request(['approval_status' => 'partially_approved'], ['accessory', 'consumable']);
        $lines = $request->items()->get();
        $actor = $this->actor('storekeeper');
        $service = $this->issuer();
        $service->issueItems($request, $actor, [$lines[0]->id => 5]);
        $this->assertSame('partially_issued', $request->fresh()->fulfillment_status);
        $service->issueItems($request, $actor, [$lines[1]->id => 5]);
        $this->assertSame('issued', $request->fresh()->fulfillment_status);
        $this->assertSame('partially_approved', $request->fresh()->approval_status);
        $this->assertSame(15, DB::table('consumables')->value('qty'));
        $this->assertSame(15, DB::table('accessories')->value('qty'));
        $this->assertSame(0, DB::table('consumables_users')->count());
        $this->assertSame(0, DB::table('accessories_checkout')->count());
        $this->assertSame(10, DB::table('action_logs')->sum('quantity'));
        $this->assertStatus(409, fn () => $service->issueItems($request, $actor, [$lines[1]->id => 1]));
    }

    public function test_force_close_releases_reservations_preserves_approval_and_rejects_replay(): void
    {
        $request = $this->request(['approval_status' => 'partially_approved']);
        $request->items()->update(['reserved_qty' => 5]);
        $actor = $this->actor('storekeeper');
        $service = $this->issuer();
        $service->forceClose($request, $actor, 'Item unavailable');
        $this->assertSame('partially_approved', $request->fresh()->approval_status);
        $this->assertSame(0, $request->items()->sum('reserved_qty'));
        $this->assertStatus(409, fn () => $service->forceClose($request, $actor, 'Item unavailable'));
        $pending = $this->request();
        $this->assertStatus(409, fn () => $service->forceClose($pending, $actor, 'Item unavailable'));
        $this->expectException(ValidationException::class);
        $service->forceClose($pending, $actor, null);
    }

    public function test_withdraw_and_receipt_are_owner_state_and_replay_guarded(): void
    {
        $request = $this->request();
        $service = app(RequesterService::class);
        $owner = $this->actor('employee', 1);
        $service->transition($request->id, $owner, 'withdraw');
        $this->assertSame('cancelled', $request->fresh()->approval_status);
        $this->assertStatus(409, fn () => $service->transition($request->id, $owner, 'withdraw'));
        $issued = $this->request(['approval_status' => 'approved', 'fulfillment_status' => 'issued']);
        $service->transition($issued->id, $owner, 'receive');
        $this->assertNotNull($issued->fresh()->received_at);
        $this->assertStatus(409, fn () => $service->transition($issued->id, $owner, 'receive'));
    }

    public function test_numbers_include_deleted_rows_and_do_not_reuse_them(): void
    {
        $first = $this->request();
        $number = $first->request_number;
        $first->delete();
        $next = $this->request();
        $this->assertNotSame($number, $next->request_number);
        $this->assertStringEndsWith('000002', $next->request_number);
    }

    public function test_audit_foreign_keys_restrict_user_purge(): void
    {
        $this->request();
        $this->expectException(QueryException::class);
        DB::table('users')->where('id', 1)->delete();
    }

    public function test_catalog_paginates_and_subtracts_native_checkouts_and_reservations(): void
    {
        DB::table('consumables_users')->insert(['consumable_id' => 1, 'assigned_to' => 1]);
        $request = $this->request(['approval_status' => 'approved']);
        $request->items()->update(['reserved_qty' => 5]);
        $page = app(CatalogService::class)->paginate(['type' => 'consumable'], 1);
        $this->assertSame(1, $page->total());
        $this->assertSame(14, $page->items()[0]->available_qty);
    }

    public function test_catalog_and_asset_reservations_exclude_other_company_stock_in_the_same_office(): void
    {
        DB::table('consumables')->where('id', 1)->update(['company_id' => 21]);
        $this->assertSame(0, app(CatalogService::class)->paginate(['type' => 'consumable'])->total());
        DB::table('models')->insert(['id' => 1, 'name' => 'Laptop', 'category_id' => 1]);
        DB::table('status_labels')->insert(['id' => 1, 'deployable' => 1, 'archived' => 0]);
        DB::table('assets')->insert(['model_id' => 1, 'status_id' => 1, 'location_id' => 10, 'company_id' => 21, 'asset_tag' => 'OTHER-COMPANY']);
        $this->assertSame(0, app(CatalogService::class)->paginate(['type' => 'asset_model'])->total());
        $this->assertSame(0, app(RequestInventory::class)->available('asset_model', AssetModel::find(1), 10));
    }

    public function test_real_goods_issue_projects_stock_once_for_consumables_and_accessories(): void
    {
        foreach (['consumable' => Consumable::class, 'accessory' => Accessory::class] as $morphType => $type) {
            DB::table('gov_inventory_movements')->insert(['id' => (string) Str::uuid(),
                'stockable_type' => $morphType, 'stockable_id' => 1, 'movement_type' => 'IN', 'quantity' => 20, 'balance_after' => 20,
                'document_type' => 'baseline', 'document_id' => 'baseline', 'company_id' => 20, 'location_id' => 10, 'created_by' => 1,
                'created_at' => now()->subDay()]);
        }
        $request = $this->request(['approval_status' => 'approved'], ['consumable', 'accessory']);
        $actor = $this->actor('storekeeper');
        app(FulfillmentService::class)->issueItems($request, $actor, $request->items()->pluck('approved_qty', 'id')->all(),
            $request->items()->get()->mapWithKeys(fn ($line) => [$line->id => ''])->all());
        $this->assertSame(15, Consumable::find(1)->numRemaining());
        $this->assertSame(15, Accessory::find(1)->numRemaining());
        $this->assertSame(1, DB::table('gov_goods_issues')->count());
        $this->assertSame(2, DB::table('gov_goods_issue_items')->count());
        $this->assertSame(2, DB::table('gov_inventory_movements')->where('movement_type', 'OUT')->count());
        $this->assertSame(2, DB::table('action_logs')->where('note', 'like', 'GovStore Stores Handshake:%')->where('created_by', $actor->id)->count());
        $document = Document::withoutGlobalScopes()->where('type', 'issue')->firstOrFail();
        $this->assertSame(1, (int) $document->issued_to_user_id);
        $this->assertSame(2, DB::table('gov_inventory_movements')->where('document_id', $document->id)->where('stockable_type', 'consumable')->count()
            + DB::table('gov_inventory_movements')->where('document_id', $document->id)->where('stockable_type', 'accessory')->count());
        $this->assertSame('issued', $request->fresh()->fulfillment_status);
    }

    public function test_quantity_and_value_thresholds_elevate_policy_without_bypassing_unknown_costs(): void
    {
        $policy = ApprovalPolicy::create(['target_type' => 'consumable', 'target_id' => 1, 'policy_name' => 'AUTO_APPROVE', 'threshold_qty' => 5]);
        $service = app(PolicyService::class);
        $this->assertSame('AUTO_APPROVE', $service->resolvePolicy('consumable', 1, 4));
        $this->assertSame('PRIMARY_AND_FINAL', $service->resolvePolicy('consumable', 1, 5));
        $policy->update(['threshold_qty' => null, 'threshold_value' => 50]);
        $this->assertSame('PRIMARY_AND_FINAL', $service->resolvePolicy('consumable', 1, 5));
        DB::table('consumables')->where('id', 1)->update(['purchase_cost' => null]);
        $this->assertSame('PRIMARY_AND_FINAL', $service->resolvePolicy('consumable', 1));
    }

    public function test_approver_requester_routes_to_independent_final_and_cover_expiry_is_respected(): void
    {
        $self = $this->actor('primary_approver', 1);
        $final = $this->actor('final_approver', 2);
        $service = app(BasketService::class);
        $service->addItem(1, 'consumable', 1);
        $request = $service->submitBasket(1, ['request_type' => 'other', 'purpose' => 'Work', 'justification' => 'Supplies'])[0];
        $this->assertSame('pending_final', $request->approval_status);
        $this->assertSame(2, $request->assigned_approver_id);
        app(ApprovalService::class)->processDecision($request, $final, $this->decisions($request, 1));
        $this->assertSame('approved', $request->fresh()->approval_status);
        DB::table('gov_office_responsibilities')->where('user_id', 2)->delete();
        DB::table('gov_access_grants')->insert(['user_id' => 2, 'location_id' => 10, 'role_slug' => 'final_approver', 'access_request_id' => 1, 'expires_at' => now()->addMinute()]);
        $routing = app(ApprovalRouting::class);
        $this->assertSame([2], $routing->candidates(10, 'final_approver', [1]));
        DB::table('gov_access_grants')->update(['expires_at' => now()->subSecond()]);
        $this->assertSame([], $routing->candidates(10, 'final_approver', [1]));
    }

    public function test_notices_are_transactional_and_overdue_escalation_is_deduplicated(): void
    {
        $actor = $this->actor('primary_approver');
        $request = $this->request();
        $other = $this->request();
        DB::table('consumables')->where('id', 1)->update(['qty' => 5]);
        app(ApprovalService::class)->processDecision($request, $actor, $this->decisions($request));
        $before = DB::table('custom_request_notices')->count();
        $this->assertStatus(409, fn () => app(ApprovalService::class)->processDecision($other, $actor, $this->decisions($other)));
        $this->assertSame($before, DB::table('custom_request_notices')->count());
        DB::table('custom_service_requests')->where('id', $other->id)->update(['updated_at' => now()->subWeekdays(4)]);
        DB::table('gov_location_profiles')->insert(['location_id' => 10, 'office_admin_id' => 2]);
        $this->artisan('gov-requests:maintain')->assertExitCode(0);
        $this->artisan('gov-requests:maintain')->assertExitCode(0);
        $this->assertSame(1, $other->events()->where('event_type', 'escalated')->count());
        $this->assertSame(1, DB::table('custom_request_notices')->where('event_key', 'escalated')->where('user_id', 2)->count());
    }

    public function test_serial_selection_rejects_duplicate_wrong_model_foreign_office_and_unavailable_assets(): void
    {
        DB::table('models')->insert(['id' => 1, 'name' => 'Laptop', 'category_id' => 1]);
        DB::table('status_labels')->insert(['id' => 1, 'deployable' => 1, 'archived' => 0]);
        DB::table('assets')->insert(['id' => 1, 'model_id' => 2, 'status_id' => 1, 'location_id' => 10, 'asset_tag' => 'WRONG-MODEL']);
        DB::table('assets')->insert(['id' => 2, 'model_id' => 1, 'status_id' => 1, 'location_id' => 10, 'asset_tag' => 'AVAILABLE']);
        $request = $this->request(['approval_status' => 'approved'], ['asset_model']);
        $line = $request->items()->first();
        $actor = $this->actor('storekeeper');
        $service = $this->issuer();
        $this->assertStatus(422, fn () => $service->issueItems($request, $actor, [$line->id => [1, 1]]));
        $this->assertStatus(422, fn () => $service->issueItems($request, $actor, [$line->id => [1]]));
        DB::table('assets')->where('id', 1)->update(['model_id' => 1, 'location_id' => 11]);
        $this->assertStatus(404, fn () => $service->issueItems($request, $actor, [$line->id => [1]]));
        DB::table('assets')->where('id', 1)->update(['location_id' => 10, 'company_id' => 21]);
        $this->assertStatus(404, fn () => $service->issueItems($request, $actor, [$line->id => [1]]));
        DB::table('assets')->where('id', 1)->update(['location_id' => 10, 'company_id' => null, 'requestable' => 0]);
        $this->assertStatus(422, fn () => $service->issueItems($request, $actor, [$line->id => [1]]));
        $this->assertNull(DB::table('assets')->where('id', 1)->value('assigned_to'));
        $this->assertSame(0, $line->fresh()->issued_qty);
    }

    public function test_substitution_restricts_category_price_and_scope_and_tracks_reservations(): void
    {
        DB::table('consumables')->insert(['id' => 2, 'name' => 'Alternative', 'category_id' => 2, 'purchase_cost' => 20]);
        $request = $this->request(['approval_status' => 'approved']);
        $line = $request->items()->first();
        $actor = $this->actor('storekeeper');
        $service = $this->issuer();
        $this->assertStatus(422, fn () => $service->issueItems($request, $actor, [$line->id => 1], [$line->id => 2]));
        DB::table('consumables')->where('id', 2)->update(['category_id' => 1]);
        $this->assertStatus(422, fn () => $service->issueItems($request, $actor, [$line->id => 1], [$line->id => 2]));
        DB::table('consumables')->where('id', 2)->update(['purchase_cost' => 5]);
        $service->issueItems($request, $actor, [$line->id => 1], [$line->id => 2]);
        $this->assertSame(2, $line->fresh()->fulfilled_id);
        $this->assertSame(4, $line->fresh()->reserved_qty);
        $event = $request->events()->where('event_type', 'item_substituted')->first();
        $this->assertSame('Paper', $event->details['original']);
        $this->assertSame('Alternative', $event->details['substituted_with']);
        $this->assertSame(15, app(RequestInventory::class)->available('consumable', Consumable::find(2), 10));
    }

    public function test_native_serial_checkout_records_a_goods_issue_and_releases_reservation(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
            $table->boolean('full_multiple_companies_support')->default(0);
        });
        DB::table('settings')->insert(['full_multiple_companies_support' => 0]);
        Event::fake([CheckoutableCheckedOut::class]);
        DB::table('models')->insert(['id' => 1, 'name' => 'Laptop', 'category_id' => 1]);
        DB::table('status_labels')->insert(['id' => 1, 'deployable' => 1, 'archived' => 0]);
        DB::table('assets')->insert(['id' => 1, 'model_id' => 1, 'status_id' => 1, 'location_id' => 10, 'asset_tag' => 'SERIAL-ONE']);
        $request = $this->request(['approval_status' => 'approved'], ['asset_model']);
        $line = $request->items()->first();
        $line->update(['approved_qty' => 1, 'reserved_qty' => 1]);
        $actor = $this->actor('storekeeper');
        $this->actingAs($actor);
        $this->issuer()->issueItems($request, $actor, [$line->id => [1]], [], 'Received at the counter');
        $this->assertSame(1, DB::table('assets')->value('assigned_to'));
        $this->assertSame(1, DB::table('assets')->value('checkout_counter'));
        Event::assertDispatched(CheckoutableCheckedOut::class);
        $this->assertSame(1, DB::table('gov_goods_issue_items')->value('stockable_id'));
        $this->assertSame(0, DB::table('gov_inventory_movements')->count());
        $this->assertSame(0, $line->fresh()->reserved_qty);
        $this->assertSame('issued', $request->fresh()->fulfillment_status);
        $this->assertSame('Received at the counter', $request->events()->where('event_type', 'item_issued')->first()->details['notes']);
    }

    public function test_native_required_metadata_failure_explains_the_error_and_rolls_back_the_issue(): void
    {
        DB::table('gov_inventory_movements')->insert(['id' => (string) Str::uuid(), 'stockable_type' => 'consumable',
            'stockable_id' => 1, 'movement_type' => 'IN', 'quantity' => 20, 'balance_after' => 20,
            'document_type' => 'opening', 'document_id' => 'baseline-opening', 'company_id' => 20, 'location_id' => 10,
            'created_by' => 1, 'created_at' => now()->subDay()]);
        $movementCountBefore = DB::table('gov_inventory_movements')->count();
        Schema::create('settings', function (Blueprint $t) {
            $t->increments('id');
            $t->boolean('full_multiple_companies_support')->default(0);
        });
        DB::table('settings')->insert(['full_multiple_companies_support' => 0]);
        Schema::table('models', fn (Blueprint $t) => $t->integer('fieldset_id')->nullable());
        Schema::table('assets', fn (Blueprint $t) => $t->string('_snipeit_grn_4')->nullable());
        Schema::create('custom_fieldsets', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
        });
        Schema::create('custom_fields', function (Blueprint $t) {
            $t->increments('id');
            $t->string('name');
            $t->string('db_column');
            $t->string('format')->default('');
            $t->boolean('field_encrypted')->default(0);
            $t->boolean('is_unique')->default(0);
        });
        Schema::create('custom_field_custom_fieldset', function (Blueprint $t) {
            $t->integer('custom_field_id');
            $t->integer('custom_fieldset_id');
            $t->boolean('required');
            $t->integer('order');
        });
        DB::table('custom_fieldsets')->insert(['id' => 1, 'name' => 'Required receipt metadata']);
        DB::table('custom_fields')->insert(['id' => 4, 'name' => 'GRN', 'db_column' => '_snipeit_grn_4']);
        DB::table('custom_field_custom_fieldset')->insert(['custom_field_id' => 4, 'custom_fieldset_id' => 1, 'required' => 1, 'order' => 0]);
        DB::table('models')->insert(['id' => 1, 'name' => 'Chair', 'category_id' => 1, 'fieldset_id' => 1]);
        DB::table('status_labels')->insert(['id' => 1, 'deployable' => 1, 'archived' => 0]);
        DB::table('assets')->insert(['id' => 1, 'model_id' => 1, 'status_id' => 1, 'location_id' => 10, 'asset_tag' => 'MISSING-GRN']);
        Event::fake([CheckoutableCheckedOut::class]);
        $request = $this->request(['approval_status' => 'approved'], ['asset_model', 'consumable']);
        $request->items()->update(['approved_qty' => 1, 'reserved_qty' => 1]);
        $lines = $request->items()->get()->keyBy('requested_type');
        $actor = $this->actor('storekeeper');
        $payload = [$lines['asset_model']->id => [1], $lines['consumable']->id => 1];
        try {
            app(FulfillmentService::class)->issueItems($request, $actor, $payload);
            $this->fail('Native required metadata must not be bypassed');
        } catch (ValidationException $e) {
            $message = implode(' ', $e->errors()['issue.'.$lines['asset_model']->id]);
            $this->assertStringContainsString('MISSING-GRN', $message);
            $this->assertStringContainsString('GRN', $message);
            $this->assertStringNotContainsString('_snipeit_grn_4', $message);
        }
        $this->assertNull(DB::table('assets')->value('assigned_to'));
        $this->assertSame(20, Consumable::find(1)->qty);
        $this->assertSame(0, $request->items()->sum('issued_qty'));
        $this->assertSame(2, $request->items()->sum('reserved_qty'));
        $this->assertSame(0, DB::table('gov_goods_issues')->count());
        $this->assertSame($movementCountBefore, DB::table('gov_inventory_movements')->count());
        Event::assertNotDispatched(CheckoutableCheckedOut::class);
        DB::table('assets')->where('id', 1)->update(['_snipeit_grn_4' => 'GR-VERIFIED']);
        app(FulfillmentService::class)->issueItems($request, $actor, $payload);
        $this->assertSame('issued', $request->fresh()->fulfillment_status);
        $this->assertSame(19, Consumable::find(1)->qty);
        $this->assertSame($movementCountBefore + 1, DB::table('gov_inventory_movements')->count());
        $this->assertSame(0, $request->items()->sum('reserved_qty'));
        Event::assertDispatched(CheckoutableCheckedOut::class);
    }

    public function test_return_intent_is_owned_and_receipt_drafting_preserves_stock_and_requires_storekeeper(): void
    {
        $request = $this->request(['approval_status' => 'approved', 'fulfillment_status' => 'issued']);
        $request->items()->update(['issued_qty' => 5]);
        $service = app(RequestReturnService::class);
        $owner = $this->actor('authenticated', 1);
        try {
            $service->requestReturn($request->id, $owner, '');
            $this->fail('Reason required');
        } catch (ValidationException) {
            $this->assertNull($request->fresh()->return_requested_at);
        }
        try {
            $service->requestReturn($request->id, $this->actor('authenticated', 5), 'Unused supplies');
            $this->fail('Another user cannot return this request');
        } catch (ModelNotFoundException) {
            $this->assertNull($request->fresh()->return_requested_at);
        }
        $service->requestReturn($request->id, $owner, 'Unused supplies');
        $this->assertStatus(409, fn () => $service->requestReturn($request->id, $owner, 'Unused supplies'));
        $this->assertStatus(403, fn () => $service->draftReceipt($request->id, $owner));
        $receipt = new Document;
        $receipt->forceFill(['id' => (string) Str::uuid(), 'document_number' => 'GR-RETURN']);
        DB::table('gov_documents')->insert(['id' => $receipt->id, 'document_number' => $receipt->document_number, 'type' => 'receipt', 'location_id' => 10]);
        $mock = Mockery::mock(GoodsReceiptService::class);
        $mock->shouldReceive('saveDraft')->once()->withArgs(fn ($header, $lines, $actorId) => $header['reference_no'] === $request->request_number
            && $lines[0]['type'] === 'consumable' && $lines[0]['qty'] === 5 && $actorId === 2)->andReturn($receipt);
        app()->instance(GoodsReceiptService::class, $mock);
        $actor = $this->actor('storekeeper');
        $this->assertSame($receipt, $service->draftReceipt($request->id, $actor));
        $this->assertSame($receipt->id, $request->fresh()->return_document_id);
        $this->assertSame(20, DB::table('consumables')->value('qty'));
        $this->assertSame(5, $request->items()->value('issued_qty'));
        $this->assertStatus(409, fn () => $service->draftReceipt($request->id, $actor));
        $this->assertFalse(app(NoPendingRequestsRule::class)->check($owner, 10)->isPassed);
        DB::table('gov_documents')->where('id', $receipt->id)->update(['status' => 'POSTED']);
        $this->assertTrue(app(NoPendingRequestsRule::class)->check($owner, 10)->isPassed);
    }

    public function test_approval_queue_matches_stage_and_keeps_the_primary_decision_in_history(): void
    {
        $primary = $this->actor('primary_approver');
        $this->actor('primary_approver', 3);
        $this->actor('final_approver', 4);
        $this->actingAs($primary);
        $pending = $this->request();
        $final = $this->request(['approval_status' => 'pending_final', 'primary_decided_by' => 3, 'resolved_policy' => 'PRIMARY_AND_FINAL']);
        $own = $this->request(['requested_by' => 2]);
        $completed = $this->request(['approval_status' => 'approved', 'primary_decided_by' => 2, 'decided_by' => 4]);
        $controller = app(GovApprovalController::class);
        $data = $controller->index()->getData();
        $this->assertSame([$pending->id], $data['pendingRequests']->pluck('id')->all());
        $this->assertSame([$completed->id], $data['processedRequests']->pluck('id')->all());
        $this->assertFalse($controller->show($final->id)->getData()['canDecide']);
        $this->assertFalse($controller->show($own->id)->getData()['canDecide']);
    }

    public function test_clearance_blocks_unresolved_historical_pending_requests_and_preserves_deleted_actor_names(): void
    {
        $owner = $this->actor('authenticated', 1);
        $request = $this->request(['office_id' => null]);
        $rule = app(NoPendingRequestsRule::class);
        $this->assertFalse($rule->check($owner, 10)->isPassed);
        $request->update(['fulfillment_status' => 'issued']);
        $this->assertTrue($rule->check($owner, 10)->isPassed);
        DB::table('users')->where('id', 1)->update(['deleted_at' => now()]);
        $this->assertNotNull($request->fresh()->requester);
    }
}
