<?php

namespace Tests\Feature\GovStore;

use GovStore\CustomRequests\Exceptions\RequestRefused;
use GovStore\CustomRequests\Http\Controllers\BasketController;
use GovStore\CustomRequests\Http\Controllers\FulfillmentRegisterController;
use GovStore\CustomRequests\Http\Controllers\GovApprovalController;
use GovStore\CustomRequests\Http\Controllers\GovFulfillmentController;
use GovStore\CustomRequests\Http\Controllers\GovRequestController;
use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Models\RequestEvent;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\BasketService;
use GovStore\CustomRequests\Services\RequestQueues;
use GovStore\CustomRequests\Support\RequestPresenter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\ViewErrorBag;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\Feature\GovStore\Concerns\CustomRequestsFixtures;

/**
 * The human-centred custom-requests redesign (docs/plans/custom-requests-ux-redesign-plan.md §7.1).
 * SQLite memory only. Pages are rendered through their controllers with a stub layout, so the tests see
 * exactly the package's markup and strings.
 */
class CustomRequestsUxTest extends TestCase
{
    use CustomRequestsFixtures {
        setUp as fixturesSetUp;
    }

    private const PKG = 'packages/gov-store/custom-requests/src';

    protected function setUp(): void
    {
        $this->fixturesSetUp();
        // A stub layout: the package's content and scripts sections without Snipe-IT's shell.
        $dir = sys_get_temp_dir().'/cr-ux-layout';
        @mkdir($dir.'/layouts', 0777, true);
        file_put_contents($dir.'/layouts/default.blade.php', "@yield('content')\n@yield('moar_scripts')");
        app('view')->getFinder()->prependLocation($dir);
        view()->share('errors', new ViewErrorBag);
        app()->setLocale('en-US');
        DB::table('users')->where('id', 1)->update(['first_name' => 'Nasrin', 'last_name' => 'Akter']);
    }

    protected function tearDown(): void
    {
        app()->setLocale('en-US');
        parent::tearDown();
    }

    private function http(string $uri = '/', string $method = 'GET', array $input = [], bool $json = false): Request
    {
        $request = Request::create($uri, $method, $input);
        if ($json) {
            $request->headers->set('Accept', 'application/json');
        }
        $request->setLaravelSession(app('session.store'));
        app()->instance('request', $request);

        return $request;
    }

    private function page(string $controller, string $method, array $params = [], array $query = []): string
    {
        $this->http('/', 'GET', $query);
        app()->forgetScopedInstances();
        $response = app()->call([app($controller), $method], $params + ['request' => app('request')]);

        return $response->render();
    }

    private function seedStore(int $consumableQty = 20): void
    {
        DB::table('consumables')->where('id', 1)->update(['qty' => $consumableQty]);
    }

    // ================================================================ P0

    public function test_every_request_status_key_has_tone_icon_and_both_labels(): void
    {
        $map = require base_path('packages/gov-store/theming/status-map.php');
        $keys = ['pending_primary', 'pending_final', 'approved', 'partly_issued', 'issued', 'received', 'closed_short',
            'rejected', 'withdrawn', 'return_requested', 'return_drafted', 'needs_review'];
        foreach ($keys as $key) {
            $entry = $map['request_'.$key] ?? null;
            $this->assertNotNull($entry, $key);
            $this->assertNotEmpty($entry['tone']);
            $this->assertStringStartsWith('fa-', $entry['icon']);
            foreach (['en-US', 'bn-BD'] as $locale) {
                $label = trans('gs-theme::appearance.status.request_'.$key, [], $locale);
                $this->assertNotSame('gs-theme::appearance.status.request_'.$key, $label, $locale.' '.$key);
            }
        }
    }

    public static function statusCases(): array
    {
        return [
            'waiting first' => [['approval_status' => 'pending_primary'], 'request_pending_primary'],
            'waiting final' => [['approval_status' => 'pending_final'], 'request_pending_final'],
            'approved, with the store' => [['approval_status' => 'approved'], 'request_approved'],
            'approved in part, with the store' => [['approval_status' => 'partially_approved'], 'request_approved'],
            'stored closed approval, unstarted' => [['approval_status' => 'closed'], 'request_approved'],
            'partly issued' => [['approval_status' => 'approved', 'fulfillment_status' => 'partially_issued'], 'request_partly_issued'],
            'issued' => [['approval_status' => 'approved', 'fulfillment_status' => 'issued'], 'request_issued'],
            'stored closed approval, issued' => [['approval_status' => 'closed', 'fulfillment_status' => 'issued'], 'request_issued'],
            'received' => [['approval_status' => 'approved', 'fulfillment_status' => 'issued', 'received_at' => '2026-10-01'], 'request_received'],
            'closed short' => [['approval_status' => 'approved', 'fulfillment_status' => 'closed'], 'request_closed_short'],
            'cannot fulfil' => [['approval_status' => 'approved', 'fulfillment_status' => 'cannot_fulfill'], 'request_closed_short'],
            'rejected' => [['approval_status' => 'rejected', 'fulfillment_status' => 'closed'], 'request_rejected'],
            'withdrawn' => [['approval_status' => 'cancelled', 'fulfillment_status' => 'closed'], 'request_withdrawn'],
            'return asked' => [['approval_status' => 'approved', 'fulfillment_status' => 'issued', 'received_at' => '2026-10-01', 'return_requested_at' => '2026-10-02'], 'request_return_requested'],
            'return being received' => [['approval_status' => 'approved', 'fulfillment_status' => 'closed', 'return_requested_at' => '2026-10-02', 'return_document_id' => 'doc'], 'request_return_drafted'],
            'historical, no office' => [['approval_status' => 'pending_primary', 'office_id' => null], 'request_needs_review'],
            'historical, no office, issued' => [['approval_status' => 'approved', 'fulfillment_status' => 'issued', 'office_id' => null], 'request_issued'],
        ];
    }

    /** @dataProvider statusCases */
    #[\PHPUnit\Framework\Attributes\DataProvider('statusCases')]
    public function test_status_key_derivation(array $attributes, string $expected): void
    {
        $request = new ServiceRequest($attributes + ['office_id' => 10, 'fulfillment_status' => 'unstarted']);
        $this->assertSame($expected, $request->statusKey());
        $this->assertArrayHasKey($expected, require base_path('packages/gov-store/theming/status-map.php'));
    }

    public function test_request_pages_render_without_missing_keys_in_both_languages(): void
    {
        $this->actor('primary_approver', 2);
        $this->actor('storekeeper', 3);
        $pending = $this->request(['request_type' => 'equipment']); // legacy stored type
        $approved = $this->request(['approval_status' => 'approved', 'approved_at' => now()]);
        RequestEvent::create(['request_id' => $pending->id, 'user_id' => 1, 'event_type' => 'submitted', 'details' => []]);
        DB::table('categories')->insert(['id' => 2, 'name' => 'Furniture']);

        foreach (['en-US', 'bn-BD'] as $locale) {
            app()->setLocale($locale);
            $this->actor('employee', 1);
            $pages = [
                $this->page(GovRequestController::class, 'catalog'),
                $this->page(GovRequestController::class, 'index'),
                $this->page(GovRequestController::class, 'index', [], ['tab' => 'finished']),
                $this->page(GovRequestController::class, 'show', ['id' => $pending->id]),
                $this->page(BasketController::class, 'index'),
            ];
            app(BasketService::class)->addItem(1, 'consumable', 1, 2);
            $pages[] = $this->page(BasketController::class, 'index');
            DB::table('draft_basket_items')->delete();

            $this->actor('primary_approver', 2);
            foreach (['waiting', 'overdue', 'decided'] as $tab) {
                $pages[] = $this->page(GovApprovalController::class, 'index', [], ['tab' => $tab]);
            }
            $pages[] = $this->page(GovApprovalController::class, 'show', ['id' => $pending->id]);

            $this->actor('storekeeper', 3);
            foreach (['ready', 'partly', 'returns'] as $tab) {
                $pages[] = $this->page(GovFulfillmentController::class, 'index', [], ['tab' => $tab]);
            }
            $pages[] = $this->page(GovFulfillmentController::class, 'show', ['id' => $approved->id]);
            $pages[] = $this->page(FulfillmentRegisterController::class, 'index');
            $pages[] = $this->page(FulfillmentRegisterController::class, 'show', ['id' => $approved->id]);

            $this->actor('superuser', 9);
            $pages[] = $this->page(GovApprovalController::class, 'policiesIndex');

            foreach ($pages as $i => $html) {
                $this->assertStringNotContainsString('requestlabels::', $html, $locale.' page '.$i);
                $this->assertStringNotContainsString('gs-theme::', $html, $locale.' page '.$i);
                $this->assertStringNotContainsString('request_type_equipment', $html, $locale.' page '.$i);
            }
        }
    }

    public function test_bn_strings_are_whole_sentences_and_use_the_glossary(): void
    {
        $en = require base_path(self::PKG.'/resources/lang/en-US/requests.php');
        $bn = require base_path(self::PKG.'/resources/lang/bn-BD/requests.php');
        $this->assertSame(array_keys($en), array_keys($bn));
        foreach ($bn as $key => $text) {
            foreach (['সার্ভিস রিকোয়েস্ট', 'ফুলফিলমেন্ট', 'রিকোয়েস্ট', 'বাস্কেট', 'ফোর্স ক্লোজ'] as $word) {
                $this->assertStringNotContainsString($word, $text, $key);
            }
            preg_match_all('/:([a-z_]+)/', $en[$key], $english);
            preg_match_all('/:([a-z_]+)/', $text, $bangla);
            $this->assertEqualsCanonicalizing(array_unique($english[1]), array_unique($bangla[1]), $key.' placeholders');
        }
        $this->assertStringContainsString('চাহিদাপত্র', $bn['nav_my_requests']);
        foreach ($en as $key => $text) {
            $this->assertDoesNotMatchRegularExpression('/^[a-z]+_[a-z_]+$/', $text, $key.' looks like a leaked key');
        }
    }

    public function test_timeline_shows_full_names_and_dated_local_times(): void
    {
        DB::table('users')->insert(['id' => 7, 'first_name' => 'Rafiq', 'last_name' => 'Ahmed', 'location_id' => 10]);
        $request = $this->request(['approval_status' => 'approved']);
        DB::table('custom_service_request_events')->insert(['request_id' => $request->id, 'user_id' => 7, 'event_type' => 'item_issued',
            'details' => json_encode(['item' => 'Paper', 'issued_qty' => 2, 'goods_issue' => 'GI-2026-000031', 'handed_to' => 'Shila Das']),
            'created_at' => '2026-10-14 10:42:00']);
        $timeline = app(RequestPresenter::class)->timeline($request->fresh(['items', 'events']));
        $this->assertSame('Rafiq Ahmed', $timeline[0]['by']);
        $this->assertSame('issued Paper ×2', $timeline[0]['action']);
        $this->assertSame('14 Oct 2026, 10:42', $timeline[0]['at']);
        $this->assertStringContainsString('Handed to Shila Das.', $timeline[0]['target']);
        $this->assertStringContainsString('GI-2026-000031', $timeline[0]['target']);
        // One history partial replaces the three timelines.
        $views = base_path(self::PKG.'/resources/views');
        $this->assertFileDoesNotExist($views.'/components/timeline-widget.blade.php');
        foreach (['user/show', 'admin/show', 'fulfillment/show', 'fulfillment-register/show'] as $view) {
            $this->assertStringContainsString("govstore::partials.history", file_get_contents($views.'/'.$view.'.blade.php'), $view);
        }
    }

    // ================================================================ P1

    public function test_over_issue_returns_to_the_page_with_the_message_and_input(): void
    {
        $this->seedStore(3);
        $request = $this->request(['approval_status' => 'approved']);
        $line = $request->items()->first();
        $this->actor('storekeeper', 3);
        $http = $this->http('/gov-requests/fulfillment/'.$request->id.'/issue', 'POST', ['issue' => [$line->id => 5], 'notes' => 'At the counter']);
        $response = app()->call([app(GovFulfillmentController::class), 'process'], ['request' => $http, 'id' => $request->id]);

        $this->assertSame(302, $response->getStatusCode());
        $message = session('errors')->first('issue.'.$line->id);
        $this->assertSame(__('requestlabels::requests.request_refused_stock', ['available' => 3, 'item' => 'Paper']), $message);
        $this->assertSame('At the counter', session('_old_input')['notes'] ?? null);
        $this->assertSame(3, (int) DB::table('consumables')->value('qty'));
        $this->assertSame(0, (int) $line->fresh()->issued_qty);
        $this->assertSame(0, DB::table('gov_goods_issues')->count());
    }

    public function test_a_second_press_on_the_same_issue_page_issues_nothing_more(): void
    {
        $this->seedStore(10);
        $request = $this->request(['approval_status' => 'approved']);
        $line = $request->items()->first();
        $this->actor('storekeeper', 3);
        $this->http('/');
        app()->instance(\GovStore\CustomRequests\Services\FulfillmentService::class, $this->issuer());
        $show = app()->call([app(GovFulfillmentController::class), 'show'], ['id' => $request->id]);
        $version = $show->getData()['version'];
        $this->assertStringContainsString('name="version" value="'.$version.'"', $show->render());

        // One of five now, then the same page sent again (a double press, or a colleague's page from before).
        $send = fn () => app()->call([app(GovFulfillmentController::class), 'process'], ['request' => $this->http('/gov-requests/fulfillment/'.$request->id.'/issue', 'POST',
            ['issue' => [$line->id => 1], 'version' => $version]), 'id' => $request->id]);
        $this->assertSame(route('gov.requests.fulfillment.index'), $send()->getTargetUrl());
        $this->assertSame(1, (int) $line->fresh()->issued_qty);

        $again = $send();
        $this->assertSame(302, $again->getStatusCode());
        $this->assertSame(__('requestlabels::requests.request_refused_changed'), session('errors')->first('request'));
        $this->assertSame(1, (int) $line->fresh()->issued_qty);
        $this->assertSame(9, (int) DB::table('consumables')->value('qty'));
    }

    public function test_stale_decision_is_explained_not_a_409_page(): void
    {
        $request = $this->request();
        $first = $this->actor('primary_approver', 2);
        app(ApprovalService::class)->processDecision($request, $first, $this->decisions($request));
        $this->actor('primary_approver', 4);
        $line = $request->items()->first();
        $http = $this->http('/gov-requests/admin/'.$request->id.'/process', 'POST', ['items' => [$line->id => ['choice' => 'decline']]]);
        $response = app()->call([app(GovApprovalController::class), 'process'], ['request' => $http, 'id' => $request->id]);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(route('gov.requests.admin.show', $request->id), $response->getTargetUrl());
        $this->assertStringStartsWith('Actor 2 decided this request', session('errors')->first('decision'));
        $this->assertSame('decline', session('_old_input')['items'][$line->id]['choice']);
        $this->assertSame('approved', $request->fresh()->approval_status);
    }

    public function test_refusal_status_codes_are_unchanged_for_json(): void
    {
        $this->seedStore(3);
        $request = $this->request(['approval_status' => 'approved']);
        $line = $request->items()->first();
        $this->actor('storekeeper', 3);
        $http = $this->http('/gov-requests/fulfillment/'.$request->id.'/issue', 'POST', ['issue' => [$line->id => 5]], true);
        try {
            app()->call([app(GovFulfillmentController::class), 'process'], ['request' => $http, 'id' => $request->id]);
            $this->fail('A JSON caller keeps the refusal status');
        } catch (RequestRefused $refusal) {
            $this->assertSame(409, $refusal->getStatusCode());
            $this->assertSame('issue.'.$line->id, $refusal->field);
        }
        $this->assertStatus(409, fn () => app(\GovStore\CustomRequests\Services\RequesterService::class)->transition($request->id, $this->actor('employee', 1), 'withdraw'));
    }

    // ================================================================ P2

    public function test_requester_can_open_only_their_own_request(): void
    {
        DB::table('users')->insert(['id' => 5, 'first_name' => 'Colleague', 'location_id' => 10]);
        $own = $this->request();
        $colleagues = $this->request(['requested_by' => 5]);
        $elsewhere = $this->request(['office_id' => 11]);
        $this->actor('employee', 1);
        $this->assertStringContainsString($own->request_number, $this->page(GovRequestController::class, 'show', ['id' => $own->id]));
        foreach ([$colleagues, $elsewhere] as $request) {
            try {
                $this->page(GovRequestController::class, 'show', ['id' => $request->id]);
                $this->fail('Not the requester, or not this office: not found');
            } catch (ModelNotFoundException) {
                $this->assertTrue(true);
            }
        }
        // An approver opening the requester route for someone else's request is not found either.
        $this->actor('primary_approver', 2);
        $this->expectException(ModelNotFoundException::class);
        $this->page(GovRequestController::class, 'show', ['id' => $own->id]);
    }

    public function test_request_page_shows_lines_approved_quantities_notes_and_next_step(): void
    {
        $request = $this->request([], ['accessory', 'consumable']);
        $lines = $request->items()->get()->keyBy('requested_type');
        $approver = $this->actor('primary_approver', 2);
        app(ApprovalService::class)->processDecision($request, $approver, [
            $lines['accessory']->id => ['status' => 'approved', 'qty' => 3, 'notes' => 'Section limit is 3 a month.'],
            $lines['consumable']->id => ['status' => 'rejected', 'qty' => 0, 'notes' => 'Enough paper in the section.'],
        ]);
        $this->actor('employee', 1);
        $html = $this->page(GovRequestController::class, 'show', ['id' => $request->id]);
        $this->assertStringContainsString('Approved 3 of 5', $html);
        $this->assertStringContainsString('Section limit is 3 a month.', $html);
        $this->assertStringContainsString('Not approved', $html);
        $this->assertStringContainsString('Enough paper in the section.', $html);
        $this->assertStringContainsString('data-status-key="request_approved"', $html);
        $this->assertStringContainsString(e(__('requestlabels::requests.next_approved', ['store' => __('requestlabels::requests.store_team')])), $html);
        $this->assertStringContainsString(e(__('requestlabels::requests.withdraw_unavailable', ['date' => now()->translatedFormat('j M Y')])), $html);
    }

    public function test_basket_split_is_previewed_and_explained(): void
    {
        $this->actor('primary_approver', 2);
        ApprovalPolicy::create(['target_type' => 'accessory', 'target_id' => 1, 'policy_name' => 'AUTO_APPROVE']);
        $this->actor('employee', 1);
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        app(BasketService::class)->addItem(1, 'accessory', 1, 1);
        $html = $this->page(BasketController::class, 'index');
        $this->assertStringContainsString(__('requestlabels::requests.split_title', ['count' => 2]), $html);
        $this->assertStringContainsString(e(__('requestlabels::requests.group_one', ['name' => 'Actor 2'])), $html);
        $this->assertStringContainsString(e(__('requestlabels::requests.group_auto')), $html);
        $this->assertStringContainsString('Send 2 requests', $html);

        $http = $this->http('/gov-requests/basket/submit', 'POST', ['request_type' => 'other', 'purpose' => 'Desk supplies', 'justification' => 'New counter', 'declared' => '1']);
        $response = app()->call([app(BasketController::class), 'submit'], ['request' => $http]);
        $batch = ServiceRequest::value('submission_batch');
        $this->assertSame(route('gov.requests.sent', $batch), $response->getTargetUrl());
        $this->assertCount(1, ServiceRequest::whereNotNull('submission_batch')->get()->groupBy('submission_batch'));
        // The Sent page names both requests and says they were sent together.
        $sent = $this->page(BasketController::class, 'sent', ['batch' => $batch]);
        foreach (ServiceRequest::pluck('request_number') as $number) {
            $this->assertStringContainsString($number, $sent);
        }
        $this->assertStringContainsString(__('requestlabels::requests.sent_together'), $sent);
        // D3: why the rule applied is stored with the request.
        $this->assertSame('default', ServiceRequest::where('resolved_policy', 'PRIMARY_ONLY')->first()->resolved_reason['lines'][0]['source']);
        $this->assertSame('item', ServiceRequest::where('resolved_policy', 'AUTO_APPROVE')->first()->resolved_reason['lines'][0]['source']);
    }

    public function test_viewing_my_requests_marks_notices_read(): void
    {
        DB::table('users')->insertOrIgnore(['id' => 2, 'first_name' => 'Actor 2', 'location_id' => 10]);
        $request = $this->request();
        RequestEvent::create(['request_id' => $request->id, 'user_id' => 2, 'event_type' => 'approved', 'details' => []]);
        $this->assertSame(1, app(\GovStore\CustomRequests\Services\RequestNotices::class)->unreadCount(1));
        $this->actor('employee', 1);
        $html = $this->page(GovRequestController::class, 'index');
        $this->assertStringContainsString(e(__('requestlabels::requests.notice_approved', ['number' => $request->request_number])), $html);
        $this->assertStringContainsString(route('gov.requests.user.show', $request->id), $html);
        $this->assertSame(0, app(\GovStore\CustomRequests\Services\RequestNotices::class)->unreadCount(1));
        $this->assertSame(0, DB::table('custom_request_notices')->where('user_id', 1)->whereNull('read_at')->count());
    }

    public function test_catalog_lists_items_without_free_stock_as_unaddable(): void
    {
        $this->seedStore(5);
        $reserved = $this->request(['approval_status' => 'approved']);
        $reserved->items()->update(['reserved_qty' => 5]);
        $this->actor('employee', 1);
        $html = $this->page(GovRequestController::class, 'catalog');
        $this->assertStringContainsString(__('requestlabels::requests.catalog_none', ['count' => 5]), $html);
        preg_match('#<li class="cr-good[^"]*cr-good--none" id="cr-item-consumable-1".*?</li>#s', $html, $row);
        $this->assertNotEmpty($row, 'The promised item stays listed');
        $this->assertStringNotContainsString('data-cr-add', $row[0]);
        $this->assertStatus(409, fn () => app(BasketService::class)->addItem(1, 'consumable', 1));
    }

    public function test_empty_draft_hides_the_send_form(): void
    {
        $this->actor('employee', 1);
        $html = $this->page(BasketController::class, 'index');
        $this->assertStringNotContainsString('data-cr-send', $html);
        $this->assertStringNotContainsString(route('gov.requests.basket.submit'), $html);
        $this->assertStringContainsString(__('requestlabels::requests.draft_empty_title'), $html);
        $this->assertStringContainsString(route('gov.requests.catalog'), $html);
    }

    // ================================================================ P3

    public function test_decline_and_reduce_without_a_reason_still_save(): void
    {
        $request = $this->request([], ['accessory', 'consumable']);
        $lines = $request->items()->get()->keyBy('requested_type');
        $this->actor('primary_approver', 2);
        $http = $this->http('/gov-requests/admin/'.$request->id.'/process', 'POST', ['items' => [
            $lines['accessory']->id => ['choice' => 'fewer', 'qty' => 2, 'notes' => ''],
            $lines['consumable']->id => ['choice' => 'decline'],
        ]]);
        $response = app()->call([app(GovApprovalController::class), 'process'], ['request' => $http, 'id' => $request->id]);
        $this->assertSame(route('gov.requests.admin.index'), $response->getTargetUrl());
        $this->assertSame('partially_approved', $request->fresh()->approval_status);
        $this->assertNull($lines['accessory']->fresh()->notes);
        $this->assertSame(2, $lines['accessory']->fresh()->approved_qty);
        $this->assertNull($lines['consumable']->fresh()->notes);

        // A reason that is given reaches the request page.
        $second = $this->request();
        $line = $second->items()->first();
        $http = $this->http('/', 'POST', ['items' => [$line->id => ['choice' => 'fewer', 'qty' => 1, 'notes' => 'One ream until the next delivery.']]]);
        app()->call([app(GovApprovalController::class), 'process'], ['request' => $http, 'id' => $second->id]);
        $this->actor('employee', 1);
        $this->assertStringContainsString('One ream until the next delivery.', $this->page(GovRequestController::class, 'show', ['id' => $second->id]));
    }

    public function test_decide_page_shows_stock_stage_and_rule_reason(): void
    {
        $this->actor('final_approver', 4);
        DB::table('users')->where('id', 4)->update(['first_name' => 'Selina', 'last_name' => 'Rahman']);
        ApprovalPolicy::create(['target_type' => 'category', 'target_id' => 1, 'policy_name' => 'PRIMARY_AND_FINAL']);
        $request = $this->request(['resolved_policy' => 'PRIMARY_AND_FINAL', 'resolved_reason' => ['lines' => [
            app(\GovStore\CustomRequests\Services\PolicyService::class)->explain('consumable', 1, 5)]]]);
        $this->actor('primary_approver', 2);
        $html = $this->page(GovApprovalController::class, 'show', ['id' => $request->id]);
        $this->assertStringContainsString(__('requestlabels::requests.stage_first'), $html);
        $this->assertStringContainsString(__('requestlabels::requests.decide_free_after', ['count' => 20, 'left' => 15]), $html);
        $this->assertStringContainsString(e(__('requestlabels::requests.reason_category_rule', ['category' => 'Supplies', 'rule' => __('requestlabels::requests.rule_two')])), $html);
        $this->assertStringContainsString(e(__('requestlabels::requests.why_two_you_first', ['final' => 'Selina Rahman'])), $html);
        $this->assertStringContainsString('name="items['.$request->items()->first()->id.'][choice]"', $html);
    }

    public function test_overdue_tab_matches_escalation(): void
    {
        $approver = $this->actor('primary_approver', 2);
        $fresh = $this->request();
        $old = $this->request();
        DB::table('custom_service_requests')->where('id', $old->id)->update(['updated_at' => now()->subWeekdays(4)]);
        $queues = app(RequestQueues::class);
        $this->assertSame([$old->id], $queues->overdue($approver)->pluck('id')->all());
        $this->artisan('gov-requests:maintain')->assertExitCode(0);
        $escalated = $this->request();
        RequestEvent::create(['request_id' => $escalated->id, 'user_id' => 1, 'event_type' => 'escalated', 'details' => []]);
        $this->assertEqualsCanonicalizing([$old->id, $escalated->id], $queues->overdue($approver)->pluck('id')->all());
        $this->assertNotContains($fresh->id, $queues->overdue($approver)->pluck('id')->all());
        $this->actor('primary_approver', 2);
        $this->http('/', 'GET', ['tab' => 'overdue']);
        $data = app()->call([app(GovApprovalController::class), 'index'], ['request' => app('request')])->getData();
        $this->assertSame(2, $data['counts']['overdue']);
        $this->assertEqualsCanonicalizing([$old->id, $escalated->id], $data['pendingRequests']->pluck('id')->all());
    }

    // ================================================================ P4

    public function test_issue_quantities_are_prefilled_to_what_can_be_issued(): void
    {
        $this->seedStore(3);
        $request = $this->request(['approval_status' => 'approved']);
        $line = $request->items()->first();
        $this->actor('storekeeper', 3);
        $this->http('/');
        $view = app()->call([app(GovFulfillmentController::class), 'show'], ['id' => $request->id]);
        $this->assertSame(3, $view->getData()['lines'][$line->id]['prefill']);
        $this->assertMatchesRegularExpression('/name="issue\['.$line->id.'\]"[^>]*value="3"/', $view->render());
        $this->seedStore(20);
        app()->forgetScopedInstances();
        $this->assertSame(5, app()->call([app(GovFulfillmentController::class), 'show'], ['id' => $request->id])->getData()['lines'][$line->id]['prefill']);
    }

    public function test_close_the_rest_requires_a_reason_and_records_it(): void
    {
        $request = $this->request(['approval_status' => 'approved']);
        $this->actor('storekeeper', 3);
        try {
            app()->call([app(GovFulfillmentController::class), 'close'], ['request' => $this->http('/', 'POST', ['reason_code' => 'out_of_stock']), 'id' => $request->id]);
            $this->fail('A reason is required');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('reason', $e->errors());
        }
        $response = app()->call([app(GovFulfillmentController::class), 'close'], ['request' => $this->http('/', 'POST',
            ['reason_code' => 'out_of_stock', 'reason' => 'Out of stock until the December delivery.']), 'id' => $request->id]);
        $this->assertSame(route('gov.requests.fulfillment.index'), $response->getTargetUrl());
        $event = $request->events()->where('event_type', 'closed')->first();
        $this->assertSame(['reason' => 'Out of stock until the December delivery.', 'reason_code' => 'out_of_stock'], $event->details);
        $this->actor('employee', 1);
        $html = $this->page(GovRequestController::class, 'show', ['id' => $request->id]);
        $this->assertStringContainsString('Out of stock until the December delivery.', $html);
        $this->assertStringContainsString('data-status-key="request_closed_short"', $html);
    }

    public function test_register_is_paginated_and_names_items(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $request = $this->request(['approval_status' => 'approved', 'fulfillment_status' => 'issued', 'closed_at' => now()->subMinutes($i)]);
            $request->items()->update(['issued_qty' => 5]);
        }
        DB::table('gov_goods_issues')->insert(['id' => $doc = (string) Str::uuid(), 'issue_no' => 'GI-SR-'.Str::uuid(), 'issue_type' => 'SYSTEM_FULFILLMENT',
            'issued_to_id' => 1, 'reference_type' => ServiceRequest::class, 'reference_id' => $request->id, 'status' => 'SUBMITTED',
            'company_id' => 20, 'location_id' => 10, 'created_by' => 3, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('gov_goods_issue_items')->insert(['goods_issue_id' => $doc, 'stockable_type' => \App\Models\Consumable::class, 'stockable_id' => 1, 'quantity' => 5]);
        $this->actor('storekeeper', 3);
        $this->http('/');
        $view = app()->call([app(FulfillmentRegisterController::class), 'index'], ['request' => app('request')]);
        $this->assertCount(25, $view->getData()['completedRequests']->items());
        $this->assertSame(30, $view->getData()['completedRequests']->total());
        $this->assertStringContainsString('Paper ×5', $view->render());
        $record = $this->page(FulfillmentRegisterController::class, 'show', ['id' => $request->id]);
        $this->assertStringContainsString('Paper', $record);
        $this->assertStringNotContainsString('>Consumable<', $record);
    }

    // ================================================================ P5

    public function test_rules_page_says_rules_apply_everywhere_and_shows_unset_categories(): void
    {
        DB::table('categories')->insert(['id' => 2, 'name' => 'Laptops']);
        ApprovalPolicy::create(['target_type' => 'category', 'target_id' => 2, 'policy_name' => 'PRIMARY_AND_FINAL']);
        $this->actor('superuser', 9);
        $html = $this->page(GovApprovalController::class, 'policiesIndex');
        $this->assertStringContainsString(__('requestlabels::requests.rules_banner_title'), $html);
        $this->assertStringContainsString(__('requestlabels::requests.rule_not_set'), $html);
        $this->assertStringContainsString(__('requestlabels::requests.rule_sentence_two'), $html);
        $this->assertStringNotContainsString('AUTO_APPROVE (', $html);
        $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html);
    }

    public function test_saving_a_rule_records_who_changed_it(): void
    {
        $this->actor('superuser', 9);
        $http = $this->http('/', 'POST', ['category_id' => 1, 'policy_name' => 'PRIMARY_ONLY', 'threshold_qty' => 10]);
        $response = app()->call([app(GovApprovalController::class), 'policiesStore'], ['request' => $http]);
        $this->assertSame(route('gov.requests.admin.policies.index'), $response->getTargetUrl());
        $policy = ApprovalPolicy::where('target_type', 'category')->where('target_id', 1)->first();
        $this->assertSame(9, (int) $policy->updated_by);
        $this->assertSame(10, (int) $policy->threshold_qty);
        // An item exception names the catalogue item, nationally, not one office's stock.
        DB::table('consumables')->insert(['id' => 2, 'name' => 'Toner', 'category_id' => 1, 'location_id' => 11]);
        app()->call([app(GovApprovalController::class), 'policiesStore'], ['request' => $this->http('/', 'POST',
            ['target_type' => 'consumable', 'target_id' => 2, 'policy_name' => 'PRIMARY_AND_FINAL', 'threshold_qty' => 4])]);
        $exception = ApprovalPolicy::where('target_type', 'consumable')->where('target_id', 2)->first();
        $this->assertNull($exception->threshold_qty, 'Limits mean nothing on "Two approvers, always"');
        app()->call([app(GovApprovalController::class), 'policiesDestroy'], ['id' => $exception->id]);
        $this->assertNull(ApprovalPolicy::find($exception->id));
    }

    // ================================================================ P6

    public function test_request_views_have_no_colour_literals_inline_styles_or_emoji(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path(self::PKG.'/resources')));
        foreach ($files as $file) {
            if (! preg_match('/\.(blade\.php|css)$/', $file)) {
                continue;
            }
            $source = file_get_contents($file);
            $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b(?![^{]*\{)|rgba?\(|hsla?\(/', preg_replace('/\{\{--.*?--\}\}|\/\*.*?\*\//s', '', $source), $file.' colour literal');
            $this->assertDoesNotMatchRegularExpression('/\sstyle="/', $source, $file.' inline style');
            $this->assertDoesNotMatchRegularExpression('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $source, $file.' emoji');
            $this->assertStringNotContainsString('cr-theme', $source, $file.' old AdminLTE re-skin');
        }
    }

    public function test_tables_have_captions_and_icon_buttons_have_names(): void
    {
        $this->actor('primary_approver', 2);
        $this->actor('storekeeper', 3);
        $request = $this->request(['approval_status' => 'approved', 'fulfillment_status' => 'issued', 'closed_at' => now()]);
        $request->items()->update(['issued_qty' => 5]);
        $pending = $this->request();
        $this->actor('employee', 1);
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        $pages = [$this->page(GovRequestController::class, 'show', ['id' => $request->id]), $this->page(BasketController::class, 'index'),
            $this->page(GovRequestController::class, 'catalog'), $this->page(GovRequestController::class, 'index')];
        $this->actor('primary_approver', 2);
        $pages[] = $this->page(GovApprovalController::class, 'show', ['id' => $pending->id]);
        $this->actor('storekeeper', 3);
        $pages[] = $this->page(FulfillmentRegisterController::class, 'index');
        $pages[] = $this->page(FulfillmentRegisterController::class, 'show', ['id' => $request->id]);

        foreach ($pages as $i => $html) {
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="utf-8"?><body>'.$html.'</body>');
            foreach ($dom->getElementsByTagName('table') as $table) {
                $this->assertSame(1, $table->getElementsByTagName('caption')->length, 'page '.$i.' table without caption');
            }
            foreach ($dom->getElementsByTagName('button') as $button) {
                $name = trim($button->textContent) ?: $button->getAttribute('aria-label');
                $this->assertNotSame('', $name, 'page '.$i.' button without a name');
            }
            foreach ($dom->getElementsByTagName('input') as $input) {
                if (in_array($input->getAttribute('type'), ['hidden', 'radio', 'checkbox'], true)) {
                    continue;
                }
                $id = $input->getAttribute('id');
                $labelled = $input->getAttribute('aria-label') || ($id && str_contains($html, 'for="'.$id.'"'));
                $this->assertTrue($labelled, 'page '.$i.' input '.$input->getAttribute('name').' without a label');
            }
        }
    }

    // ================================================================ Request flow redesign (catalog, checkout, sent)

    private function send(array $input = []): \Illuminate\Http\RedirectResponse
    {
        $http = $this->http('/gov-requests/basket/submit', 'POST', $input + ['request_type' => 'other', 'purpose' => 'Desk supplies',
            'justification' => 'New counter', 'declared' => '1']);

        return app()->call([app(BasketController::class), 'submit'], ['request' => $http]);
    }

    private function ajax(string $method, array $input, array $params = []): array
    {
        $http = $this->http('/', 'POST', $input);
        $http->headers->set('X-Requested-With', 'XMLHttpRequest');
        app()->forgetScopedInstances();

        return app()->call([app(BasketController::class), $method], $params + ['request' => $http])->getData(true);
    }

    public function test_catalog_shows_who_approves_and_keeps_cards_or_list_for_the_session(): void
    {
        $this->actor('employee', 1);
        $html = $this->page(GovRequestController::class, 'catalog');
        $this->assertStringContainsString('cr-goods--grid', $html);
        $this->assertStringContainsString(__('requestlabels::requests.badge_rule_one'), $html);
        $this->assertStringContainsString('data-cr-type-count="consumable">1<', $html);

        $this->assertStringContainsString('cr-goods--list', $this->page(GovRequestController::class, 'catalog', [], ['view' => 'list']));
        // The choice is kept without the query value.
        $this->assertStringContainsString('cr-goods--list', $this->page(GovRequestController::class, 'catalog'));

        ApprovalPolicy::create(['target_type' => 'consumable', 'target_id' => 1, 'policy_name' => 'AUTO_APPROVE']);
        $this->assertStringContainsString(__('requestlabels::requests.badge_rule_auto'), $this->page(GovRequestController::class, 'catalog', [], ['type' => 'consumable']));
    }

    public function test_draft_changes_return_fresh_panel_card_and_checkout_parts(): void
    {
        $this->seedStore(20);
        $this->actor('employee', 1);
        $added = $this->ajax('add', ['item_type' => 'consumable', 'item_id' => 1, 'qty' => 1, 'context' => 'catalog']);
        $this->assertSame(1, $added['count']);
        $this->assertStringContainsString('Paper', $added['fragments']['drawer']);
        $this->assertSame('cr-item-consumable-1', $added['fragments']['item_id']);
        $this->assertStringContainsString('data-cr-line-form', $added['fragments']['item'], 'Add became a quantity');

        $updated = $this->ajax('updateQty', ['item_id' => $added['line']['id'], 'qty' => 3, 'context' => 'checkout']);
        $this->assertStringContainsString('value="3"', $updated['fragments']['lines']);
        $this->assertArrayHasKey('summary', $updated['fragments']);
        $this->assertArrayHasKey('groups', $updated['fragments']);
        $this->assertSame(3, json_decode($updated['reviewed'], true)[0]['qty']);
        $this->assertTrue($updated['needs_reason']);

        $removed = $this->ajax('remove', ['context' => 'catalog'], ['itemId' => $added['line']['id']]);
        $this->assertSame(0, $removed['count']);
        $this->assertStringContainsString(__('requestlabels::requests.draft_empty_title'), $removed['fragments']['drawer']);
    }

    public function test_drafts_are_kept_per_office_and_an_older_draft_is_claimed(): void
    {
        $this->actor('employee', 1);
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        $this->assertSame(10, \GovStore\CustomRequests\Models\DraftBasket::first()->office_id);

        // In another office the draft is a different one, and office 10's items are not offered there.
        app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId = 11;
        $this->assertNull(\GovStore\CustomRequests\Models\DraftBasket::openFor(1)->first());
        $other = app(BasketService::class)->getOrCreateDraftBasket(1);
        $this->assertSame(11, $other->office_id);
        $this->assertCount(0, $other->items);
        app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId = 10;
        $this->assertCount(1, \GovStore\CustomRequests\Models\DraftBasket::openFor(1)->first()->items);

        // A draft saved before drafts had an office belongs to the office it is next used in.
        \GovStore\CustomRequests\Models\DraftBasket::query()->delete();
        $legacy = \GovStore\CustomRequests\Models\DraftBasket::create(['user_id' => 1, 'status' => 'draft', 'expires_at' => now()->addDay()]);
        $this->assertSame($legacy->id, app(BasketService::class)->getOrCreateDraftBasket(1)->id);
        $this->assertSame(10, $legacy->fresh()->office_id);
    }

    public function test_send_refuses_a_draft_changed_since_review_and_says_what_changed(): void
    {
        $this->seedStore(20);
        $this->actor('primary_approver', 2);
        $this->actor('employee', 1);
        $basket = app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        $reviewed = app(BasketService::class)->snapshot($basket);
        app(BasketService::class)->updateItemQty(1, $basket->items()->first()->id, 4);

        $response = $this->send(['reviewed' => json_encode($reviewed)]);
        $this->assertSame(0, ServiceRequest::count(), 'Nothing is sent unseen');
        $this->assertSame(__('requestlabels::requests.request_refused_draft_changed'), session('errors')->first('reviewed'));
        $this->assertSame([__('requestlabels::requests.changed_qty', ['item' => 'Paper', 'qty' => 4, 'before' => 2])], session('cr_changes'));
        $this->assertNull(session()->getOldInput('declared'), 'The declaration is confirmed again');

        // Free stock moving without crossing the asked quantity is not a change.
        $reviewed = app(BasketService::class)->snapshot($basket->fresh());
        $this->seedStore(15);
        $this->send(['reviewed' => json_encode($reviewed)]);
        $this->assertSame(1, ServiceRequest::count());
    }

    public function test_a_second_send_with_the_same_token_sends_nothing_and_shows_the_first(): void
    {
        $this->actor('primary_approver', 2);
        $this->actor('employee', 1);
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        $token = (string) Str::uuid();
        $first = $this->send(['submit_token' => $token]);
        $again = $this->send(['submit_token' => $token]);
        $this->assertSame(1, ServiceRequest::count());
        $this->assertSame($first->getTargetUrl(), $again->getTargetUrl());
        $this->assertSame(__('requestlabels::requests.already_sent'), session('info'));
    }

    public function test_declaration_is_required_and_recorded(): void
    {
        $this->actor('primary_approver', 2);
        $this->actor('employee', 1);
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        try {
            $this->send(['declared' => null]);
            $this->fail('Sent without the declaration');
        } catch (ValidationException $e) {
            $this->assertSame([__('requestlabels::requests.declare_required')], $e->errors()['declared']);
        }
        $this->send();
        $details = RequestEvent::where('event_type', 'submitted')->first()->details;
        $this->assertSame('official_use_v1', $details['declaration']);
        $this->assertNotEmpty($details['declared_at']);
    }

    public function test_a_reason_is_needed_only_when_someone_approves(): void
    {
        $this->actor('primary_approver', 2);
        $this->actor('employee', 1);
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        $this->send(['justification' => '']);
        $this->assertSame(0, ServiceRequest::count());
        $this->assertSame(__('requestlabels::requests.request_refused_reason_needed'), session('errors')->first('justification'));

        ApprovalPolicy::create(['target_type' => 'consumable', 'target_id' => 1, 'policy_name' => 'AUTO_APPROVE']);
        $this->send(['justification' => '']);
        $this->assertSame('approved', ServiceRequest::sole()->approval_status);
        $this->assertSame('', ServiceRequest::sole()->justification);
    }

    public function test_checkout_has_three_steps_the_snapshot_a_token_and_the_declaration(): void
    {
        $this->actor('primary_approver', 2);
        $this->actor('employee', 1);
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        $html = $this->page(BasketController::class, 'index');
        foreach ([1, 2, 3] as $step) {
            $this->assertStringContainsString('data-cr-panel="'.$step.'"', $html);
        }
        $this->assertMatchesRegularExpression('/name="submit_token" value="[0-9a-f-]{36}"/', $html);
        $this->assertStringContainsString('name="reviewed" value="'.e(json_encode(app(BasketService::class)->snapshot(\GovStore\CustomRequests\Models\DraftBasket::first()))).'"', $html);
        $this->assertStringContainsString('name="declared"', $html);
        $this->assertStringContainsString(e(__('requestlabels::requests.read_by', ['name' => 'Actor 2'])), $html);
    }

    public function test_sent_page_is_only_for_its_owner_in_that_office(): void
    {
        $this->actor('primary_approver', 2);
        $this->actor('employee', 1);
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        $this->send();
        $batch = ServiceRequest::value('submission_batch');
        $this->assertStringContainsString(ServiceRequest::value('request_number'), $this->page(BasketController::class, 'sent', ['batch' => $batch]));

        $this->actor('employee', 5);
        $this->assertStatus(404, fn () => $this->page(BasketController::class, 'sent', ['batch' => $batch]));
        $this->actor('employee', 1);
        app(\GovStore\TenantScope\Contexts\TenantContext::class)->locationId = 11;
        $this->assertStatus(404, fn () => $this->page(BasketController::class, 'sent', ['batch' => $batch]));
    }

    public function test_draft_changes_and_sending_are_rate_limited(): void
    {
        $routes = app('router')->getRoutes();
        foreach (['add', 'update', 'remove'] as $name) {
            $this->assertContains('throttle:gov-requests-draft', $routes->getByName('gov.requests.basket.'.$name)->gatherMiddleware());
        }
        $this->assertContains('throttle:gov-requests-send', $routes->getByName('gov.requests.basket.submit')->gatherMiddleware());
        $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('gov-requests-draft'));
        $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('gov-requests-send'));
    }
}
