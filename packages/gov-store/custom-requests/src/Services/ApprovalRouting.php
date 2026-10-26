<?php

namespace GovStore\CustomRequests\Services;

use GovStore\CustomRequests\Models\Request;
use Illuminate\Support\Facades\DB;

class ApprovalRouting
{
    /** Responsibilities and unexpired cover grants share the authoritative GovAccess role sources. */
    public function candidates(int $office, string $role, array $exclude = []): array
    {
        $ids = DB::table('gov_office_responsibilities')->where('location_id', $office)->where('role_slug', $role)->pluck('user_id')
            ->merge(DB::table('gov_access_grants')->where('location_id', $office)->where('role_slug', $role)->where('expires_at', '>', now())->pluck('user_id'));

        $company = DB::table('locations')->where('id', $office)->whereNull('deleted_at')->value('company_id');
        return DB::table('users')->whereIn('id', $ids)->whereNotIn('id', $exclude)->whereNull('deleted_at')->where('activated', true)
            ->where('company_id', $company)->orderBy('id')->pluck('id')
            ->filter(fn ($id) => app(\GovStore\TenantScope\Contracts\MembershipContextResolver::class)->hasActiveMembershipAt((int) $id, $office))->values()->all();
    }

    /** A path needs different people, and neither may approve their own request. */
    public function feasible(int $office, string $policy, array $exclude = []): bool
    {
        if ($policy === 'AUTO_APPROVE') {
            return true;
        }
        $primary = $this->candidates($office, 'primary_approver', $exclude);
        $final = $this->candidates($office, 'final_approver', $exclude);
        if ($policy === 'PRIMARY_ONLY') {
            return (bool) ($primary || $final);
        }
        foreach ($primary as $actor) {
            if (array_diff($final, [$actor])) {
                return true;
            }
        }

        return false;
    }

    public function assign(Request $request): array
    {
        $exclude = array_filter([$request->requested_by, $request->primary_decided_by]);
        $role = $request->approval_status === 'pending_final' ? 'final_approver' : 'primary_approver';
        $candidates = $this->candidates($request->office_id, $role, $exclude);
        if ($role === 'primary_approver' && $request->resolved_policy === 'PRIMARY_AND_FINAL') {
            $candidates = array_values(array_filter($candidates,
                fn ($id) => (bool) $this->candidates($request->office_id, 'final_approver', array_merge($exclude, [$id]))));
        }
        if (! $candidates && $role === 'primary_approver' && $request->resolved_policy === 'PRIMARY_ONLY') {
            $candidates = $this->candidates($request->office_id, 'final_approver', $exclude);
            if ($candidates) {
                $request->approval_status = 'pending_final';
            }
        }
        $request->assigned_approver_id = $candidates[0] ?? null;
        $request->save();

        return $candidates;
    }

    public function blocked(Request $request): bool
    {
        if ($request->approval_status === 'pending_final') {
            return ! $this->candidates((int) $request->office_id, 'final_approver', array_filter([$request->requested_by, $request->primary_decided_by]));
        }

        return $request->approval_status === 'pending_primary'
            && ! $this->feasible((int) $request->office_id, $request->resolved_policy, [(int) $request->requested_by]);
    }
}
