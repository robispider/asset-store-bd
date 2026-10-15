<?php

namespace Tests\Feature\GovStore;

use App\Models\User;
use Carbon\Carbon;
use GovStore\StoreOperations\Http\Controllers\DocumentWorkspaceController;
use GovStore\StoreOperations\Models\Document;
use GovStore\StoreOperations\Services\DocumentHub;
use GovStore\TenantScope\Services\AccessDecision;
use GovStore\TenantScope\Services\GovAccess;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

/**
 * The redesigned store documents hub (design canvas 88jU4Acab6By7gypnGUaQY): one office's documents,
 * what needs the signed-in person, the details panel and the start-a-document dialog.
 * Isolated SQLite and a stub layout; user 2 (Kamal) is the storekeeper signed in.
 */
class StoreDocumentsHubTest extends StoreOperationsWorkflowTest
{
    private bool $seeded = false;

    protected function setUp(): void
    {
        parent::setUp();
        foreach (['suppliers', 'companies'] as $table) {
            Schema::create($table, function (Blueprint $t) { $t->increments('id'); $t->string('name'); $t->timestamp('deleted_at')->nullable(); });
        }
        Schema::create('locations', function (Blueprint $t) {
            $t->increments('id'); $t->string('name'); $t->integer('company_id')->nullable(); $t->string('address')->nullable(); $t->string('city')->nullable();
            $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->increments('id'); $t->string('first_name'); $t->string('last_name')->nullable(); $t->string('username'); $t->string('jobtitle')->nullable(); $t->timestamp('deleted_at')->nullable();
        });
        Schema::create('gov_document_notices', function (Blueprint $t) {
            $t->id(); $t->uuid('document_id'); $t->unsignedInteger('user_id'); $t->unsignedInteger('actor_id'); $t->string('event_key'); $t->timestamp('created_at')->useCurrent();
        });

        DB::table('companies')->insert(['id' => 20, 'name' => 'Ministry of Test']);
        DB::table('locations')->insert([['id' => 10, 'name' => 'Central Store', 'company_id' => 20], ['id' => 11, 'name' => 'Branch Store B', 'company_id' => 20]]);
        DB::table('suppliers')->insert(['id' => 7, 'name' => 'Rahman Traders']);
        DB::table('users')->insert([['id' => 1, 'first_name' => 'Nasrin', 'last_name' => 'Akter', 'username' => 'nasrin'],
            ['id' => 2, 'first_name' => 'Kamal', 'last_name' => 'Hossain', 'username' => 'kamal']]);

        $dir = sys_get_temp_dir().'/storeops-hub-layout';
        @mkdir($dir.'/layouts', 0777, true);
        file_put_contents($dir.'/layouts/default.blade.php', "@yield('content')@yield('moar_scripts')");
        app('view')->getFinder()->prependLocation($dir);
        app('view')->getDispatcher()->forget('composing: layouts.default');
        view()->share('errors', new ViewErrorBag);
        app()->setLocale('en-US');
        $this->abilities(draft: true, post: true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app()->setLocale('en-US');
        parent::tearDown();
    }

    private function abilities(bool $draft, bool $post): void
    {
        auth()->setUser(User::withoutGlobalScopes()->findOrFail(2));
        $allowed = fn ($ability) => $ability === 'storeops.documents.view' || ($draft && $ability === 'storeops.documents.draft') || ($post && $ability === 'storeops.documents.post');
        app()->instance(GovAccess::class, Mockery::mock(GovAccess::class)
            ->shouldReceive('decide')->andReturnUsing(fn ($user, $ability) => new AccessDecision($allowed($ability), $ability, 'test'))
            ->shouldReceive('permitsRequest')->andReturnUsing(fn ($user, $ability) => $allowed($ability))->getMock());
    }

    /** Office 10: Kamal's draft, Nasrin's draft, a ready transfer, a posted receipt and a voided draft. Office 11 has its own. */
    private function documents(): void
    {
        if ($this->seeded) {
            return;
        }
        $this->seeded = true;
        $doc = function (string $at, array $attributes) {
            Carbon::setTestNow(Carbon::parse($at));

            return Document::withoutGlobalScopes()->create(array_merge(['company_id' => 20, 'location_id' => 10, 'created_by' => 2], $attributes));
        };

        $posted = $doc('2026-10-12 13:10', ['document_number' => 'GR-2026-0146', 'type' => 'receipt', 'status' => 'POSTED', 'purchase_type' => 'Purchase',
            'supplier_id' => 7, 'managed_by' => 2, 'posted_by' => 1, 'posted_at' => now()]);
        $posted->references()->create(['reference_type' => 'Challan', 'reference_number' => 'CH-7690']);
        $posted->timelines()->create(['state' => 'POSTED', 'user_id' => 1]);
        $doc('2026-10-09 15:40', ['document_number' => 'GR-2026-0145', 'type' => 'receipt', 'status' => 'CANCELLED', 'managed_by' => 2]);
        $doc('2026-10-14 12:30', ['document_number' => 'TR-2026-0012', 'type' => 'transfer', 'status' => 'READY', 'managed_by' => 1, 'created_by' => 1,
            'destination_location_id' => 11, 'transfer_reason' => 'Rebalance stock']);
        $other = $doc('2026-10-15 08:50', ['document_number' => 'ADJ-2026-0007', 'type' => 'adjustment', 'status' => 'DRAFT', 'managed_by' => 1,
            'adjustment_reason' => 'PHYSICAL_COUNT', 'source_document_id' => $posted->id]);
        $other->timelines()->create(['state' => 'DRAFT', 'user_id' => 1, 'notes' => 'Draft taken over from manager 2. Reason: on leave']);
        DB::table('gov_document_notices')->insert(['document_id' => $other->id, 'user_id' => 2, 'actor_id' => 1, 'event_key' => 'takeover', 'created_at' => now()]);
        $mine = $doc('2026-10-15 10:42', ['document_number' => 'GI-2026-0091', 'type' => 'issue', 'status' => 'DRAFT', 'managed_by' => 2,
            'issue_department' => 'Accounts Section']);
        $mine->references()->create(['reference_type' => 'Memo', 'reference_number' => 'MEMO-112']);
        $doc('2026-10-15 11:00', ['document_number' => 'GR-OTHER-0001', 'type' => 'receipt', 'status' => 'DRAFT', 'managed_by' => 2, 'location_id' => 11]);

        Carbon::setTestNow(Carbon::parse('2026-10-15 14:00'));
    }

    private function hub(array $query = [])
    {
        $this->documents();
        $request = Request::create('/gov-store/operations/hub', 'GET', $query);
        app()->instance('request', $request);

        return app(DocumentWorkspaceController::class)->hub($request, app(DocumentHub::class));
    }

    private function id(string $number): string
    {
        $this->documents();
        return Document::withoutGlobalScopes()->where('document_number', $number)->value('id');
    }

    private function numbers(array $query = []): array
    {
        return collect($this->hub($query)->getData()['documents']->items())->pluck('number')->all();
    }

    public function test_hub_lists_the_working_office_by_latest_activity_with_what_needs_me(): void
    {
        $view = $this->hub();
        $data = $view->getData();

        $this->assertSame(['GI-2026-0091', 'ADJ-2026-0007', 'TR-2026-0012', 'GR-2026-0146', 'GR-2026-0145'], collect($data['documents']->items())->pluck('number')->all());
        $this->assertSame(['all' => 5, 'drafts' => 1, 'ready' => 1, 'posted' => 1, 'posted_month' => 1], $data['counts']);

        $rows = collect($data['documents']->items())->keyBy('number');
        $this->assertSame(['continue', 'view', 'verify', 'view', 'view'], $rows->pluck('action')->values()->all());
        $this->assertSame('To Accounts Section', $rows['GI-2026-0091']['party']);
        $this->assertSame('Physical count · cites GR-2026-0146', $rows['ADJ-2026-0007']['party']);
        $this->assertSame('To Branch Store B', $rows['TR-2026-0012']['party']);
        $this->assertSame('Purchase · Rahman Traders', $rows['GR-2026-0146']['party']);
        $this->assertSame('Nasrin Akter', $rows['GR-2026-0146']['handler']);
        $this->assertSame('Today, 10:42', $rows['GI-2026-0091']['activity']);

        $html = $view->render();
        $this->assertStringNotContainsString('<x-gs', $html);
        $this->assertStringNotContainsString('<x-gov', $html);
        foreach (['Central Store', 'What needs you', 'Your drafts', 'Ready to verify', 'Posted this month', 'How a document moves',
            'Start a document', 'Nasrin Akter took over your draft ADJ-2026-0007.', 'Every save, takeover and post is recorded',
            route('storeops.documents.workspace', ['type' => 'issue', 'id' => $this->id('GI-2026-0091')]), 'id="storeops-start"',
            'name="document_type" value="adjustment"', route('storeops.documents.initialize')] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        // Another office's document never reaches this hub, not even as a count.
        $this->assertStringNotContainsString('GR-OTHER-0001', $html);
    }

    public function test_tabs_type_and_search_narrow_the_list(): void
    {
        $this->assertSame(['GI-2026-0091'], $this->numbers(['filter' => 'drafts']));
        $this->assertSame(['TR-2026-0012'], $this->numbers(['filter' => 'ready']));
        $this->assertSame(['GR-2026-0146'], $this->numbers(['filter' => 'posted']));
        $this->assertSame(['GR-2026-0146', 'GR-2026-0145'], $this->numbers(['type' => 'receipt']));
        $this->assertSame(['GR-2026-0146'], $this->numbers(['q' => 'ch-7690']));
        $this->assertSame(['GI-2026-0091'], $this->numbers(['q' => 'accounts']));
        $this->assertSame([], $this->numbers(['q' => '%']));

        $html = $this->hub(['q' => 'nothing like this'])->render();
        $this->assertStringContainsString('No documents match.', $html);
        $this->assertStringContainsString('Clear filters', $html);

        $this->expectException(ValidationException::class);
        $this->hub(['filter' => 'everything']);
    }

    public function test_details_panel_shows_progress_trail_and_only_the_actions_the_state_allows(): void
    {
        $html = $this->hub(['doc' => $this->id('ADJ-2026-0007')])->render();
        foreach (['id="storeops-panel"', 'This draft is being handled by Nasrin Akter', 'Take over draft', 'minlength="5"',
            route('storeops.documents.takeover', ['type' => 'adjustment', 'id' => $this->id('ADJ-2026-0007')]), 'Before it can be posted',
            'worked on the draft', 'on leave'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('Void this draft', $html);

        $html = $this->hub(['doc' => $this->id('GI-2026-0091')])->render();
        $this->assertStringContainsString('Continue in workspace', $html);
        $this->assertStringContainsString(route('storeops.documents.void', ['type' => 'issue', 'id' => $this->id('GI-2026-0091')]), $html);
        $this->assertStringNotContainsString('Take over draft', $html);

        $html = $this->hub(['doc' => $this->id('GR-2026-0146')])->render();
        foreach (['Locked in the ledger.', route('storeops.documents.print', ['type' => 'receipt', 'id' => $this->id('GR-2026-0146')]),
            'Correct with adjustment', 'posted it to the ledger'] as $expected) {
            $this->assertStringContainsString($expected, $html);
        }
        $this->assertStringNotContainsString('Before it can be posted', $html);

        $html = $this->hub(['doc' => $this->id('GR-2026-0145')])->render();
        $this->assertStringContainsString('Draft · voided', $html);
        $this->assertStringContainsString('kept for the audit trail', $html);
    }

    public function test_panel_for_another_offices_document_is_not_found(): void
    {
        try {
            $this->hub(['doc' => $this->id('GR-OTHER-0001')]);
            $this->fail('A document from another office must not open in the hub.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
    }

    public function test_people_see_why_an_action_is_unavailable(): void
    {
        $this->abilities(draft: true, post: false);
        $html = $this->hub(['doc' => $this->id('TR-2026-0012')])->render();
        $this->assertStringContainsString('Only an officer with posting authority can post.', $html);
        $this->assertStringContainsString('Posting is paused for this office.', $html);

        DB::table('gov_store_ledger_openings')->insert(['location_id' => 10, 'document_id' => $this->id('GR-2026-0145'),
            'opened_at' => now(), 'opened_by' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->abilities(draft: true, post: true);
        $html = $this->hub(['doc' => $this->id('TR-2026-0012')])->render();
        $this->assertStringNotContainsString('Posting is paused for this office.', $html);
        $this->assertStringContainsString('You will see the final preview and confirm', $html);

        $this->abilities(draft: false, post: false);
        $html = $this->hub()->render();
        $this->assertStringNotContainsString('id="storeops-start"', $html);
        $this->assertStringNotContainsString('Start a document', $html);
    }

    public function test_start_dialog_opens_on_the_chosen_type_and_hub_renders_in_bangla(): void
    {
        $html = $this->hub(['start' => 'adjustment'])->render();
        $this->assertMatchesRegularExpression('/<dialog[^>]*id="storeops-start"[^>]*open/', $html);
        $this->assertMatchesRegularExpression('/id="storeops-start-adjustment"[^>]*checked/', $html);

        app()->setLocale('bn-BD');
        $html = $this->hub()->render();
        $this->assertStringContainsString('আপনার যা করণীয়', $html);
        $this->assertStringContainsString('যাচাইয়ের অপেক্ষায়', $html);
    }
}
