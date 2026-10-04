<?php

namespace GovStore\Committee\Clearance;

use App\Models\User;
use GovStore\OfficeMembership\Contracts\IClearanceRule;
use GovStore\OfficeMembership\Services\ClearanceResult;
use GovStore\Committee\Models\CommitteeTenure;

class NoUnplannedSeatVacancyRule implements IClearanceRule
{
    public function getName(): string { return __('committee::committee.clearance'); }
    public function check(User $user, int $locationId): ClearanceResult
    {
        $count = CommitteeTenure::where('user_id',$user->id)->where('home_location_id_snapshot',$locationId)->where('from_date','<=',now('Asia/Dhaka')->toDateString())
            ->whereNotIn('id',CommitteeTenure::whereNotNull('corrects_tenure_id')->select('corrects_tenure_id'))
            ->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date','>=',now('Asia/Dhaka')->toDateString()))
            ->whereIn('committee_id',\GovStore\Committee\Models\Committee::where('status','ACTIVE')->pluck('id'))->count();
        if ($count && config('committee.clearance.mode') === 'block_if_inoperable') {
            $ids = CommitteeTenure::where('user_id',$user->id)->where('home_location_id_snapshot',$locationId)->pluck('committee_id');
            foreach (\GovStore\Committee\Models\Committee::whereIn('id',$ids)->where('status','ACTIVE')->get() as $committee) {
                $findings = app(\GovStore\Committee\Services\CompositionValidator::class)->evaluate($committee,now('Asia/Dhaka')->toDateString(),$user->id);
                if (collect($findings)->contains(fn ($finding) => $finding['severity'] === 'BLOCK')) {
                    return new ClearanceResult(false,__('committee::committee.clearance_seats',['count'=>$count]));
                }
            }
        }
        return new ClearanceResult(true,__('committee::committee.clearance_seats',['count'=>$count]));
    }
}
