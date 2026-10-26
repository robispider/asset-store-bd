<?php

namespace Tests\Feature\GovStore;

use GovStore\CustomRequests\Models\ApprovalPolicy;
use GovStore\CustomRequests\Models\DraftBasket;
use GovStore\CustomRequests\Models\Request as ServiceRequest;
use GovStore\CustomRequests\Services\ApprovalService;
use GovStore\CustomRequests\Services\BasketService;
use GovStore\CustomRequests\Services\RequestQueues;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\GovStore\Concerns\CustomRequestsFixtures;

/** Submission-authority policy; every case runs in isolated SQLite memory. */
class RequesterAuthorityApprovalTest extends TestCase
{
    use CustomRequestsFixtures;

    public static function submissionCases(): array
    {
        $cases = [];
        foreach (['employee', 'primary_approver', 'final_approver', 'superuser'] as $role) {
            foreach (['AUTO_APPROVE', 'PRIMARY_ONLY', 'PRIMARY_AND_FINAL'] as $policy) {
                $automatic = $policy === 'AUTO_APPROVE' || $role === 'final_approver'
                    || ($role === 'primary_approver' && $policy === 'PRIMARY_ONLY');
                $status = $automatic ? 'approved' : ($role === 'primary_approver' ? 'pending_final' : 'pending_primary');
                $authority = $policy === 'AUTO_APPROVE' || ! in_array($role, ['primary_approver', 'final_approver']) ? null : $role;
                $cases[$role.' / '.$policy] = [$role, $policy, $status, $authority];
            }
        }

        return $cases;
    }

    #[DataProvider('submissionCases')]
    public function test_submission_uses_current_authority_and_preserves_product_rule(string $role, string $policy, string $status, ?string $authority): void
    {
        $this->actor($role, 1);
        $this->policy($policy);
        $basket = app(BasketService::class)->addItem(1, 'consumable', 1, 3);
        $group = app(BasketService::class)->preview($basket)[$policy];
        $this->assertSame($status, $group['submission']['status']);
        $this->assertSame($status === 'approved', $group['approval_available']);
        $request = $this->submit();
        $this->assertSame($policy, $request->resolved_policy);
        $this->assertSame($status, $request->approval_status);
        $this->assertSame($authority, $request->resolved_reason['submission']['authority']);
        $this->assertSame('requester_authority_v1', $request->resolved_reason['submission']['rule']);
        $this->assertSame($policy, $request->resolved_reason['lines'][0]['policy']);
        $this->assertNull($request->assigned_approver_id);
        $this->assertNull($request->approved_by);
        $this->assertNull($request->primary_decided_by);
        $this->assertNull($request->decided_by);
        $line = $request->items()->first();
        $this->assertSame($status === 'pending_primary' ? 0 : 3, $line->approved_qty);
        $this->assertSame($status === 'approved' ? 3 : 0, $line->reserved_qty);
        $this->assertSame($status === 'approved' ? 'approved' : 'pending', $line->line_approval_status);
        $this->assertSame($status === 'approved' ? 'waiting' : 'unstarted', $line->line_fulfillment_status);
        $event = $request->events()->first();
        $this->assertSame($status === 'approved' ? 'auto_approved' : 'submitted', $event->event_type);
        $this->assertSame($request->resolved_reason['submission'], $event->details['submission']);
        $this->assertSame($status === 'approved', $request->approved_at !== null);
    }

    public function test_primary_requester_must_obtain_independent_final_decision_with_original_quantity_cap(): void
    {
        $owner = $this->actor('primary_approver', 1);
        $final = $this->actor('final_approver', 2);
        $this->policy('PRIMARY_AND_FINAL');
        $basket = app(BasketService::class)->addItem(1, 'consumable', 1, 3);
        $this->assertTrue(app(BasketService::class)->preview($basket)['PRIMARY_AND_FINAL']['approval_available']);
        $request = $this->submit();
        $this->assertSame('pending_final', $request->approval_status);
        $this->assertSame(2, $request->assigned_approver_id);
        $this->assertSame(0, $request->items()->sum('reserved_qty'));
        $this->assertTrue(app(RequestQueues::class)->awaitingApproval($final)->whereKey($request->id)->exists());
        $this->assertFalse(app(RequestQueues::class)->awaitingApproval($owner)->whereKey($request->id)->exists());
        foreach (['shadow', 'enforce'] as $mode) {
            config(['govstore-access.mode' => $mode]);
            $this->assertStatus(403, fn () => app(ApprovalService::class)->processDecision($request, $owner, $this->decisions($request, 3)));
        }
        $result = app(ApprovalService::class)->processDecision($request, $final, $this->decisions($request, 99));
        $this->assertSame('approved', $result->approval_status);
        $this->assertSame(3, $result->items()->first()->approved_qty);
        $this->assertSame(3, $result->items()->first()->reserved_qty);
        $this->assertSame(2, $result->approved_by);
        $this->assertNull($result->primary_decided_by);
        $this->assertStatus(409, fn () => app(ApprovalService::class)->processDecision($result, $final, $this->decisions($result)));
    }

    public function test_final_stage_cannot_be_forged_without_server_submission_evidence(): void
    {
        $request = $this->request(['resolved_policy' => 'PRIMARY_AND_FINAL', 'approval_status' => 'pending_final']);
        $final = $this->actor('final_approver');
        $this->assertStatus(409, fn () => app(ApprovalService::class)->processDecision($request, $final, $this->decisions($request)));
        $this->assertSame(0, $request->items()->sum('reserved_qty'));
        $this->assertSame(0, $request->events()->count());
    }

    public function test_requester_holding_both_roles_uses_their_final_authority(): void
    {
        $this->actor('primary_approver', 1);
        $this->actor('final_approver', 1);
        $this->policy('PRIMARY_AND_FINAL');
        app(BasketService::class)->addItem(1, 'consumable', 1);
        $request = $this->submit();
        $this->assertSame('approved', $request->approval_status);
        $this->assertSame('final_approver', $request->resolved_reason['submission']['authority']);
        $this->assertSame(1, $request->items()->sum('reserved_qty'));
    }

    public function test_missing_independent_final_stays_pending_and_notifies_office_administrator(): void
    {
        $this->actor('primary_approver', 1);
        $this->actor('employee', 9);
        Schema::table('gov_location_profiles', fn (Blueprint $t) => $t->string('lifecycle_status')->default('operational'));
        DB::table('gov_location_profiles')->insert(['location_id' => 10, 'office_admin_id' => 9]);
        $this->policy('PRIMARY_AND_FINAL');
        app(BasketService::class)->addItem(1, 'consumable', 1);
        $request = $this->submit();
        $this->assertSame('pending_final', $request->approval_status);
        $this->assertNull($request->assigned_approver_id);
        $this->assertSame(0, $request->items()->sum('reserved_qty'));
        $this->assertTrue(DB::table('custom_request_notices')->where('request_id', $request->id)->where('user_id', 9)->exists());
    }

    public static function invalidAuthorityCases(): array
    {
        return array_map(fn ($case) => [$case], ['other_office', 'other_company', 'inactive_membership', 'inactive_account', 'expired_cover', 'revoked_role', 'native_admin']);
    }

    #[DataProvider('invalidAuthorityCases')]
    public function test_invalid_authority_never_auto_approves_even_in_shadow(string $case): void
    {
        $this->actor('final_approver', 1);
        config(['govstore-access.mode' => 'shadow']);
        switch ($case) {
            case 'other_office':
                DB::table('gov_office_responsibilities')->where('user_id', 1)->update(['location_id' => 11]);
                break;
            case 'other_company':
                DB::table('users')->where('id', 1)->update(['company_id' => 21]);
                break;
            case 'inactive_membership':
                DB::table('gov_office_memberships')->where('user_id', 1)->update(['status' => 'inactive']);
                break;
            case 'inactive_account':
                DB::table('users')->where('id', 1)->update(['activated' => false]);
                break;
            default:
                DB::table('gov_office_responsibilities')->where('user_id', 1)->delete();
                if ($case === 'expired_cover') {
                    $this->cover(now()->subSecond());
                } elseif ($case === 'native_admin') {
                    DB::table('users')->where('id', 1)->update(['permissions' => json_encode(['admin' => '1'])]);
                }
        }
        app(BasketService::class)->addItem(1, 'consumable', 1);
        $request = $this->submit();
        $this->assertSame('pending_primary', $request->approval_status);
        $this->assertNull($request->resolved_reason['submission']['authority']);
        $this->assertSame(0, $request->items()->sum('reserved_qty'));
    }

    public function test_active_cover_qualifies_but_expiry_since_review_requires_new_review(): void
    {
        $this->actor('employee', 1);
        $this->cover(now()->addMinute());
        $service = app(BasketService::class);
        $basket = $service->addItem(1, 'consumable', 1);
        $reviewed = $service->snapshot($basket);
        $this->assertSame('approved', $reviewed[0]['submission']['status']);
        DB::table('gov_access_grants')->update(['expires_at' => now()->subSecond()]);
        $this->assertStatus(409, fn () => $this->submit($reviewed));
        $this->assertSame(0, ServiceRequest::count());
        $this->assertSame(1, DraftBasket::count());
        $this->assertSame('authority', $service->changes($reviewed, $service->snapshot($basket))[0]['change']);
        $this->assertSame('pending_primary', $this->submit($service->snapshot($basket))->approval_status);
    }

    public function test_active_cover_auto_approves_without_creating_permanent_role(): void
    {
        $this->actor('employee', 1);
        $this->cover(now()->addMinute());
        $this->policy('PRIMARY_AND_FINAL');
        app(BasketService::class)->addItem(1, 'consumable', 1);
        $this->assertSame('approved', $this->submit()->approval_status);
        $this->assertFalse(DB::table('gov_office_responsibilities')->where('user_id', 1)->where('role_slug', 'final_approver')->exists());
    }

    public function test_mixed_rules_and_quantity_threshold_still_require_higher_authority(): void
    {
        $this->actor('primary_approver', 1);
        $this->actor('final_approver', 2);
        $this->policy('PRIMARY_ONLY', ['threshold_qty' => 3]);
        $service = app(BasketService::class);
        $service->addItem(1, 'consumable', 1, 3);
        $service->addItem(1, 'accessory', 1, 2);
        $requests = $service->submitBasket(1, $this->metadata());
        $this->assertCount(2, $requests);
        $requests = collect($requests)->keyBy('resolved_policy');
        $this->assertSame('approved', $requests['PRIMARY_ONLY']->approval_status);
        $this->assertSame(2, $requests['PRIMARY_ONLY']->items()->sum('reserved_qty'));
        $this->assertSame('pending_final', $requests['PRIMARY_AND_FINAL']->approval_status);
        $this->assertSame('qty', $requests['PRIMARY_AND_FINAL']->resolved_reason['lines'][0]['threshold']);
        $this->assertSame(0, $requests['PRIMARY_AND_FINAL']->items()->sum('reserved_qty'));
        $this->assertSame($requests['PRIMARY_ONLY']->submission_batch, $requests['PRIMARY_AND_FINAL']->submission_batch);
    }

    public function test_authority_auto_approval_rolls_back_entire_submission_when_stock_is_short(): void
    {
        $this->actor('final_approver', 1);
        $service = app(BasketService::class);
        $service->addItem(1, 'accessory', 1, 2);
        $service->addItem(1, 'consumable', 1, 3);
        DB::table('consumables')->where('id', 1)->update(['qty' => 2]);
        $this->assertStatus(409, fn () => $this->submit());
        $this->assertSame(0, ServiceRequest::count());
        $this->assertSame(0, DB::table('custom_service_request_events')->count());
        $this->assertSame(0, DB::table('custom_request_notices')->count());
        $this->assertSame(2, DraftBasket::first()->items()->count());
    }

    public static function thresholdCases(): array
    {
        return [
            'primary / value' => ['primary_approver', false, 'pending_final'],
            'primary / unknown cost' => ['primary_approver', true, 'pending_final'],
            'final / value' => ['final_approver', false, 'approved'],
            'final / unknown cost' => ['final_approver', true, 'approved'],
        ];
    }

    #[DataProvider('thresholdCases')]
    public function test_value_threshold_is_resolved_before_requester_authority(string $role, bool $unknownCost, string $status): void
    {
        $this->actor($role, 1);
        $this->policy('AUTO_APPROVE', ['threshold_value' => 20]);
        if ($unknownCost) {
            DB::table('consumables')->where('id', 1)->update(['purchase_cost' => null]);
        }
        app(BasketService::class)->addItem(1, 'consumable', 1, 2);
        $request = $this->submit();
        $this->assertSame('PRIMARY_AND_FINAL', $request->resolved_policy);
        $this->assertSame($status, $request->approval_status);
        $this->assertSame($unknownCost ? 'cost' : 'value', $request->resolved_reason['lines'][0]['threshold']);
        $this->assertSame($status === 'approved' ? 2 : 0, $request->items()->sum('reserved_qty'));
    }

    public function test_submission_metadata_cannot_claim_authority_or_change_the_product_rule(): void
    {
        $this->actor('employee', 1);
        $this->policy('PRIMARY_AND_FINAL');
        app(BasketService::class)->addItem(1, 'consumable', 1);
        $request = app(BasketService::class)->submitBasket(1, $this->metadata() + [
            'resolved_policy' => 'AUTO_APPROVE', 'approval_status' => 'approved',
            'resolved_reason' => ['submission' => ['rule' => 'requester_authority_v1', 'status' => 'approved', 'authority' => 'final_approver']],
        ])[0];
        $this->assertSame('PRIMARY_AND_FINAL', $request->resolved_policy);
        $this->assertSame('pending_primary', $request->approval_status);
        $this->assertNull($request->resolved_reason['submission']['authority']);
        $this->assertSame(0, $request->items()->sum('reserved_qty'));
    }

    private function policy(string $policy, array $extra = []): void
    {
        ApprovalPolicy::create($extra + ['target_type' => 'consumable', 'target_id' => 1, 'policy_name' => $policy]);
    }

    private function cover($expires): void
    {
        DB::table('gov_access_grants')->insert(['user_id' => 1, 'location_id' => 10, 'role_slug' => 'final_approver',
            'access_request_id' => 1, 'expires_at' => $expires]);
    }

    private function metadata(): array
    {
        return ['request_type' => 'other', 'purpose' => 'Office supplies', 'justification' => 'Official use'];
    }

    private function submit(?array $reviewed = null): ServiceRequest
    {
        return app(BasketService::class)->submitBasket(1, $this->metadata(), $reviewed)[0];
    }
}
