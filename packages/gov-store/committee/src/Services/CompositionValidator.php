<?php

namespace GovStore\Committee\Services;

use Carbon\CarbonImmutable;
use GovStore\Committee\Models\{Committee,CommitteeTenure,SeatRole,ExternalMember};
use Illuminate\Support\Facades\DB;

class CompositionValidator
{
    public function evaluate(Committee $committee, string $date, ?int $excludedUserId = null): array
    {
        $policy = $committee->policy_snapshot ?? $committee->type->composition_policy;
        $issues = [];
        $add = function ($code,$severity = 'BLOCK') use (&$issues) {
            $issues[] = ['code'=>$code,'severity'=>$severity,'message_en'=>__('committee::committee.issues.'.$code,[], 'en-US'),'message_bn'=>__('committee::committee.issues.'.$code,[], 'bn-BD')];
        };
        // Corrections invalidate the original and replace it with their own dated tenure.
        $all = CommitteeTenure::where('committee_id',$committee->id)->get();
        $invalid = $all->pluck('corrects_tenure_id')->filter()->all();
        $rows = $all->filter(fn ($r) => ! in_array($r->id,$invalid) && $r->from_date <= $date && (! $r->to_date || $r->to_date >= $date));
        $seats = $committee->seats->keyBy('id');
        $roles = SeatRole::all()->keyBy('code');
        $count = $presiding = $secretary = $external = $expert = 0;
        $people = [];
        foreach ($rows as $tenure) {
            $seat = $seats[$tenure->seat_id] ?? null;
            $role = $seat ? ($roles[$seat->seat_role_code] ?? null) : null;
            if (! $role) { $add('INVALID_SEAT'); continue; }
            $userId = $tenure->user_id;
            if ($tenure->external_member_id) {
                $ext = ExternalMember::find($tenure->external_member_id);
                if ($ext?->linked_at && substr($ext->linked_at,0,10) <= $date) { $userId = $ext->linked_user_id; }
            }
            if ($userId) {
                if (in_array($userId,$people)) { $add('DUPLICATE_PERSON'); }
                $people[] = $userId;
            }
            if ($excludedUserId && $userId === $excludedUserId) { continue; }
            $eligible = true;
            // Current account state cannot reconstruct historical HR facts; past appointments use their frozen eligibility.
            if ($tenure->user_id && $date >= CarbonImmutable::now('Asia/Dhaka')->toDateString()) {
                $user = DB::table('users')->where('id',$tenure->user_id)->whereNull('deleted_at')->where('activated',1)->first();
                if (! $user) { $add('MEMBER_ACCOUNT_DISABLED','WARN'); $eligible = false; }
                if (! DB::table('gov_office_memberships')->where('user_id',$tenure->user_id)->where('location_id',$tenure->home_location_id_snapshot)->where('status','active')->exists()) {
                    $add('MEMBER_LEFT_OFFICE','WARN'); $eligible = false;
                }
            }
            if (! $eligible) { continue; }
            $count += (int)$role->counts_toward_strength;
            $presiding += (int)($role->is_presiding && in_array($role->code,$policy['presiding']['roles']));
            $secretary += (int)$role->is_secretary;
            $expert += (int)($role->code === 'technical_expert');
            $outside = $policy['external']['outside'] === 'ministry'
                ? ($tenure->home_company_id_snapshot && (int)$tenure->home_company_id_snapshot !== (int)$committee->owner_company_id)
                : ($tenure->home_location_id_snapshot && (int)$tenure->home_location_id_snapshot !== (int)$committee->owner_location_id);
            // Unknown government affiliation is not evidence of being outside a ministry.
            if ($tenure->external_member_id && ! $tenure->home_company_id_snapshot) {
                $outside = in_array(ExternalMember::find($tenure->external_member_id)?->organization_kind,['UNIVERSITY','PRIVATE_EXPERT','DEVELOPMENT_PARTNER'],true);
            }
            $external += (int)($role->counts_toward_strength && $outside);
            if (($policy['declaration_required'] ?? false) && $tenure->declaration_status !== 'FILED') { $add('DECLARATION_PENDING','WARN'); }
            foreach ($policy['incompatible_duties'] ?? [] as $duty) {
                if ($userId && DB::table('gov_office_responsibilities')->where('user_id',$userId)->where('location_id',$committee->owner_location_id)->where('role_slug',$duty['duty'])->exists()) {
                    $add('INCOMPATIBLE_DUTY',$duty['severity']);
                }
            }
        }
        if ($count < $policy['strength']['min']) { $add('BELOW_MIN_STRENGTH'); }
        if ($count > $policy['strength']['max']) { $add('ABOVE_MAX_STRENGTH'); }
        if (($policy['strength']['odd_only'] ?? false) && $count % 2 === 0) { $add('ODD_STRENGTH_REQUIRED'); }
        if ($presiding !== $policy['presiding']['exactly']) { $add('PRESIDING_VACANT'); }
        if ($secretary < $policy['secretary']['min']) { $add('SECRETARY_VACANT','WARN'); }
        if ($secretary > $policy['secretary']['max']) { $add('TOO_MANY_SECRETARIES'); }
        if ($external < $policy['external']['min']) { $add('EXTERNAL_SHORTFALL'); }
        if ($expert < $policy['technical_expert']['min']) { $add('TECHNICAL_EXPERT_SHORTFALL'); }
        if (! $committee->scopes()->where('effective_from','<=',$date)->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to','>=',$date))->exists()) { $add('NO_COVERAGE'); }
        if ($committee->effective_to && ($policy['max_term_months'] ?? null) && CarbonImmutable::parse($committee->effective_from)->addMonths($policy['max_term_months'])->subDay()->toDateString() < $committee->effective_to) { $add('TERM_TOO_LONG'); }
        return $issues;
    }
}
