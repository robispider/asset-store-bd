<?php

namespace Tests\CustomRequestsMysql;

use App\Models\Company;
use App\Models\Consumable;
use App\Models\Location;
use App\Models\User;
use GovStore\CustomRequests\Models\Request;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/** Opt-in only: uses the guarded empty MySQL schema clone, never the development database. */
class RequestMysqlConcurrencyTest extends TestCase
{
    public function createApplication()
    {
        require_once __DIR__.'/mysql-bootstrap.php';

        return \customRequestsMysqlApplication();
    }

    private function worker(array $arguments): array
    {
        $process = proc_open(array_merge([PHP_BINARY, '-d', 'xdebug.mode=off', __DIR__.'/mysql-worker.php'], $arguments),
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), null, ['bypass_shell' => true]);
        $this->assertIsResource($process);
        fclose($pipes[0]);

        return [$process, $pipes];
    }

    private function workerResult(array $worker): array
    {
        [$process,$pipes] = $worker;
        $output = stream_get_contents($pipes[1]);
        $errors = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $this->assertSame(0, proc_close($process), $errors);
        $this->assertJson($output, 'Worker output: '.$output.'; stderr: '.$errors);

        return json_decode($output, true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_reservation_reads_current_commit_even_after_a_repeatable_read_snapshot(): void
    {
        $company = Company::factory()->create();
        $office = Location::factory()->create(['company_id' => $company->id]);
        // Isolated fixtures omit legacy onboarding, which assumes a development user ID of 1.
        $owner = User::factory()->createQuietly(['location_id' => $office->id, 'company_id' => $company->id]);
        $actor = User::factory()->createQuietly(['location_id' => $office->id, 'company_id' => $company->id, 'permissions' => json_encode(['superuser' => '1'])]);
        $context = app(TenantContext::class);
        $context->locationId = $office->id;
        $context->companyId = $company->id;
        $stock = Consumable::factory()->create(['location_id' => $office->id, 'company_id' => $company->id, 'qty' => 10]);
        $requests = [];
        for ($i = 0; $i < 2; $i++) {
            $request = Request::create(['office_id' => $office->id, 'requested_by' => $owner->id, 'request_type' => 'other',
                'purpose' => 'Isolated locking check', 'justification' => 'Concurrent demand', 'resolved_policy' => 'PRIMARY_ONLY',
                'approval_status' => 'pending_primary', 'fulfillment_status' => 'unstarted']);
            $request->items()->create(['requested_type' => 'consumable', 'requested_id' => $stock->id, 'requested_qty' => 10]);
            $requests[] = $request;
        }
        DB::statement('SET SESSION TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        DB::beginTransaction();
        try {
            $line = $requests[1]->items()->first(); // Establish an older consistent-read snapshot.
            $result = $this->workerResult($this->worker([(string) $requests[0]->id, (string) $actor->id]));
            $this->assertSame('approved', $result['approval_status']);
            $this->assertSame(0, (int) DB::table('custom_service_request_items')->where('request_id', $requests[0]->id)->value('reserved_qty'));
            try {
                app(ApprovalService::class)->processDecision($requests[1], $actor, [$line->id => ['status' => 'approved', 'qty' => 10]]);
                $this->fail('The last ten units were already reserved by the other connection.');
            } catch (HttpExceptionInterface $error) {
                $this->assertSame(409, $error->getStatusCode());
            }
        } finally {
            DB::rollBack();
        }
        $this->assertSame('pending_primary', $requests[1]->fresh()->approval_status);
        $this->assertSame(0, $requests[1]->items()->value('reserved_qty'));
        $this->assertSame(10, $requests[0]->items()->value('reserved_qty'));
        $this->assertSame(10, $stock->fresh()->qty);
    }

    public function test_four_independent_connections_allocate_one_hundred_distinct_numbers(): void
    {
        $year = max(2099, (int) DB::table('custom_request_sequences')->max('year') + 1);
        $workers = [];
        for ($i = 0; $i < 4; $i++) {
            $workers[] = $this->worker(['numbers', (string) $year]);
        }
        $numbers = [];
        foreach ($workers as $worker) {
            $numbers = array_merge($numbers, $this->workerResult($worker));
        }
        $this->assertCount(100, $numbers);
        $this->assertCount(100, array_unique($numbers));
        $this->assertSame(100, (int) DB::table('custom_request_sequences')->where('year', $year)->value('last_number'));
    }
}
