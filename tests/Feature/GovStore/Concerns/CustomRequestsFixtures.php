<?php

namespace Tests\Feature\GovStore\Concerns;

use App\Models\User;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\FulfillmentService;
use GovStore\StoreOperations\Contracts\StockIssuingServiceInterface;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** SQLite memory schema and helpers shared by the custom-requests workflow and UX suites. */
trait CustomRequestsFixtures
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
        foreach (['2024_01_04_000000_create_draft_basket_tables.php', '2026_10_04_000001_harden_custom_request_workflow.php', '2026_10_04_000002_add_request_thresholds_and_notices.php', '2026_10_04_000003_add_request_returns.php', '2026_10_15_000001_add_request_explanations.php', '2026_10_15_000002_scope_drafts_to_offices.php'] as $file) {
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
        DB::table('consumables')->insert(['id' => 1, 'name' => 'Paper', 'category_id' => 1, 'purchase_cost' => 10, 'company_id' => 20]);
        DB::table('accessories')->insert(['id' => 1, 'name' => 'Keyboard', 'category_id' => 1, 'purchase_cost' => 10, 'company_id' => 20]);
    }

    protected function actor(string $role, int $id = 2): User
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

    protected function request(array $attributes = [], array $types = ['consumable']): ServiceRequest
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

    protected function assertStatus(int $expected, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected denial '.$expected);
        } catch (HttpExceptionInterface $e) {
            $this->assertSame($expected, $e->getStatusCode());
        }
    }

    protected function decisions($request, int $qty = 5): array
    {
        return $request->items()->get()->mapWithKeys(fn ($line) => [$line->id => ['status' => 'approved', 'qty' => $qty]])->all();
    }

    protected function issuer(): FulfillmentService
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
}
