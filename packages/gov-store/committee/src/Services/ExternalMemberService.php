<?php

namespace GovStore\Committee\Services;

use GovStore\Committee\Models\{ExternalMember,Committee};
use GovStore\Committee\Policies\CommitteePolicy;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\{Validator,DB};

class ExternalMemberService
{
    public function __construct(private CommitteePolicy $policy, private TenantContext $context, private MemberDirectory $directory, private CommitteeLedger $ledger) {}
    public function create(array $input): ExternalMember
    {
        $this->policy->ability('committee.manage'); abort_unless($this->context->locationId && $this->context->companyId,422);
        $data = Validator::make($input,['full_name_en'=>'required|string|max:150','full_name_bn'=>'required|string|max:150','designation_en'=>'required|string|max:150','designation_bn'=>'required|string|max:150',
            'organization_name_en'=>'required|string|max:200','organization_name_bn'=>'required|string|max:200','organization_kind'=>'required|in:GOVERNMENT,AUTONOMOUS,UNIVERSITY,PRIVATE_EXPERT,DEVELOPMENT_PARTNER,OTHER',
            'mobile'=>'nullable|string|max:30','email'=>'nullable|email|max:255','expected_to_onboard'=>'sometimes|boolean','home_location_id'=>'nullable|integer','home_company_id'=>'nullable|integer'])->validate();
        if (! empty($data['home_location_id'])) {
            $office = DB::table('locations')->where('id',$data['home_location_id'])->whereNull('deleted_at')->first(); abort_unless($office,404);
            abort_unless((int)$office->company_id === (int)($data['home_company_id'] ?? 0),422);
        }
        if (! empty($data['home_company_id'])) { abort_unless(DB::table('companies')->where('id',$data['home_company_id'])->whereNull('deleted_at')->exists(),404); }
        return ExternalMember::create($data + ['owner_company_id'=>$this->context->companyId,'created_by'=>auth()->id()]);
    }
    public function link(int $id, string $code): ExternalMember
    {
        $this->policy->ability('committee.manage');
        return DB::transaction(function () use ($id,$code) {
            $external = ExternalMember::where('owner_company_id',$this->context->companyId)->whereKey($id)->lockForUpdate()->firstOrFail();
            abort_if($external->linked_user_id,409); $person = $this->directory->byCode($code);
            $committees = Committee::whereHas('tenures',fn ($q) => $q->where('external_member_id',$id))->orderBy('lineage_id')->get();
            foreach ($committees as $c) {
                $this->policy->check($c,'committee.manage',true);
                abort_if($c->tenures()->where('user_id',$person['user_id'])->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date','>=',now('Asia/Dhaka')->toDateString()))->exists(),409);
            }
            $external->update(['linked_user_id'=>$person['user_id'],'linked_at'=>now(),'linked_by'=>auth()->id()]);
            foreach ($committees as $c) { $this->ledger->append($c,'ExternalMemberLinked',['external_member_id'=>$id,'user_id'=>$person['user_id']]); }
            return $external;
        });
    }
}
