<?php

namespace GovStore\Committee\Services;

use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\{DB,Log};

class MemberDirectory
{
    public function __construct(private TenantContext $context) {}
    public function byCode(string $code): array
    {
        $token = DB::table('gov_employee_verification_tokens')->where('token',$code)->where('expires_at','>',now())->whereNull('used_at')->first();
        abort_unless($token && hash_equals($token->token,$code),422);
        $person = $this->person((int)$token->user_id,false);
        Log::notice('Committee identity lookup',['actor_id'=>auth()->id(),'identified_user_id'=>$token->user_id,'office_id'=>$this->context->locationId]);
        return $person;
    }
    public function person(int $id, bool $requireBoundary = true): array
    {
        $user = DB::table('users')->where('id',$id)->where('activated',1)->whereNull('deleted_at')->first();
        abort_unless($user,404);
        $membership = DB::table('gov_office_memberships')->where('user_id',$id)->where('status','active')->orderByDesc('is_home_office')->first();
        abort_unless($membership,422);
        $location = DB::table('locations')->where('id',$membership->location_id)->whereNull('deleted_at')->first();
        abort_unless($location,422);
        if ($requireBoundary) {
            // A visitor can be visible through an active membership in the
            // working office while their appointment snapshot retains the home
            // office. Never confuse home affiliation with picker visibility.
            $visible = DB::table('gov_office_memberships as memberships')->join('locations as offices','offices.id','=','memberships.location_id')
                ->where('memberships.user_id',$id)->where('memberships.status','active')->whereNull('offices.deleted_at')
                ->where('offices.company_id',$this->context->companyId)
                ->whereIn('memberships.location_id',$this->context->allowedLocationIds ?? [$this->context->locationId ?? 0])->exists();
            abort_unless($visible,404);
        }
        return ['user_id'=>$id,'name_en'=>trim($user->first_name.' '.$user->last_name),'name_bn'=>$user->display_name ?: trim($user->first_name.' '.$user->last_name),
            'designation_en'=>$user->jobtitle ?? '', 'designation_bn'=>$user->jobtitle ?? '', 'home_location_id'=>(int)$location->id,'home_company_id'=>(int)$location->company_id,'office_name'=>$location->name];
    }
}
