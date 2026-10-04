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

        return DB::table('users')->whereIn('id', $ids)->whereNotIn('id', $exclude)->whereNull('deleted_at')
            ->orderBy('id')->pluck('id')->all();
    }

    public function assign(Request $request): array
    {
        $exclude = array_filter([$request->requested_by, $request->primary_decided_by]);
        $role = $request->approval_status === 'pending_final' ? 'final_approver' : 'primary_approver';
        $candidates = $this->candidates($request->office_id, $role, $exclude);
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
}
