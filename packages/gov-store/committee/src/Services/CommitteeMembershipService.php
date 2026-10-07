<?php

namespace GovStore\Committee\Services;

use Carbon\CarbonImmutable;
use GovStore\Committee\Models\{Committee,CommitteeSeat,CommitteeTenure,SeatRole,ExternalMember};
use Illuminate\Support\Facades\Validator;

class CommitteeMembershipService
{
    public function __construct(private CommitteeService $committees, private MemberDirectory $directory, private CommitteeLedger $ledger, private CompositionValidator $composition) {}
    public function addSeat(string $id, array $input): CommitteeSeat
    {
        return $this->committees->mutate($id,'committee.manage',['DRAFT'],function ($c) use ($input) {
            $data = Validator::make($input,['seat_role_code'=>'required|string','seat_no'=>'required|integer|min:1|max:100','holder_kind'=>'required|in:PERSON,POST,EXTERNAL',
                'post_title_en'=>'required_if:holder_kind,POST|nullable|string|max:200','post_title_bn'=>'required_if:holder_kind,POST|nullable|string|max:200','post_location_id'=>'nullable|integer','is_required'=>'sometimes|boolean'])->validate();
            abort_unless(SeatRole::where('code',$data['seat_role_code'])->where('is_active',true)->exists(),422);
            if (! empty($data['post_location_id'])) { app(\GovStore\Committee\Policies\CommitteePolicy::class)->scope(new \GovStore\Committee\DTOs\ScopeRef('office',(string)$data['post_location_id'])); }
            $seat = $c->seats()->create($data + ['created_by'=>auth()->id()]);
            $this->ledger->append($c,'SeatAdded',['seat_id'=>$seat->id]); return $seat;
        });
    }
    public function removeSeat(string $id, int $seatId): void
    {
        $this->committees->mutate($id,'committee.manage',['DRAFT'],function ($c) use ($seatId) {
            $seat = $c->seats()->findOrFail($seatId); $c->tenures()->where('seat_id',$seatId)->delete(); $seat->delete();
            $this->ledger->append($c,'SeatRemoved',['seat_id'=>$seatId]);
        });
    }
    public function updateDraftSeat(string $id, int $seatId, array $input): CommitteeSeat
    {
        return $this->committees->mutate($id,'committee.manage',['DRAFT'],function ($c) use ($seatId,$input) {
            $data = Validator::make($input,['seat_role_code'=>'required|string','seat_no'=>'required|integer|min:1|max:100'])->validate();
            abort_unless(SeatRole::where('code',$data['seat_role_code'])->where('is_active',true)->exists(),422);
            $seat = $c->seats()->findOrFail($seatId);
            $other = $c->seats()->where('seat_no',$data['seat_no'])->whereKeyNot($seatId)->first();
            if ($other) { $previous = $seat->seat_no; $seat->update(['seat_no'=>101]); $other->update(['seat_no'=>$previous]); }
            $seat->update($data);
            $this->ledger->append($c,'DraftUpdated',['seat_id'=>$seat->id]); return $seat;
        });
    }
    public function appoint(string $id, int $seatId, array $input): CommitteeTenure
    {
        unset($input['_correcting_tenure_id']);
        return $this->committees->mutate($id,'committee.manage',['DRAFT','ACTIVE'],function ($c) use ($seatId,$input) {
            $tenure = $this->appointLocked($c,$seatId,$input);
            $this->afterChange($c,$input); return $tenure;
        });
    }
    private function appointLocked(Committee $c, int $seatId, array $input): CommitteeTenure
    {
        $seat = $c->seats()->findOrFail($seatId);
        Validator::make($input,['from_date'=>'required|date_format:Y-m-d|after_or_equal:'.$c->effective_from,
            'to_date'=>'nullable|date_format:Y-m-d|after_or_equal:from_date','order_id'=>'required|integer','user_id'=>'nullable|integer','external_member_id'=>'nullable|integer',
            'remarks'=>'nullable|string|max:1000','verification_code'=>'nullable|string|max:100'])->validate();
        $from = $input['from_date']; $to = $input['to_date'] ?? null;
        abort_if($c->effective_to && ($from > $c->effective_to || ($to && $to > $c->effective_to)),422);
        $order = $this->committees->order($c,(int)$input['order_id'],$c->status === 'DRAFT' ? ['CONSTITUTION','RECONSTITUTION'] : ['AMENDMENT','CORRIGENDUM'],$from);
        $all = $c->tenures()->get(); $invalid = $all->pluck('corrects_tenure_id')->filter()->all();
        if (isset($input['_correcting_tenure_id'])) { $invalid[] = $input['_correcting_tenure_id']; }
        $overlapping = $all->filter(fn ($t) => ! in_array($t->id,$invalid) && $t->from_date <= ($to ?? '9999-12-31') && (! $t->to_date || $t->to_date >= $from));
        abort_if($overlapping->contains('seat_id',$seatId),409);
        $userId = ! empty($input['user_id']) ? (int)$input['user_id'] : null;
        $externalId = ! empty($input['external_member_id']) ? (int)$input['external_member_id'] : null;
        abort_unless((bool)$userId !== (bool)$externalId && (($seat->holder_kind === 'EXTERNAL') === (bool)$externalId),422);
        if ($externalId) {
            $external = ExternalMember::where('owner_company_id',$c->owner_company_id)->findOrFail($externalId);
            $person = ['name_en'=>$external->full_name_en,'name_bn'=>$external->full_name_bn,'designation_en'=>$external->designation_en,'designation_bn'=>$external->designation_bn,
                'home_location_id'=>$external->home_location_id,'home_company_id'=>$external->home_company_id];
            $identity = $external->linked_user_id;
        } else {
            $person = ! empty($input['verification_code']) ? $this->directory->byCode($input['verification_code']) : $this->directory->person($userId);
            abort_unless($person['user_id'] === $userId,422); $identity = $userId;
        }
        foreach ($overlapping as $existing) {
            $other = $existing->user_id ?? ExternalMember::find($existing->external_member_id)?->linked_user_id;
            abort_if(($identity && $other === $identity) || ($externalId && $existing->external_member_id === $externalId),409);
        }
        $tenure = $c->tenures()->create(['seat_id'=>$seatId,'user_id'=>$userId,'external_member_id'=>$externalId,
            'name_snapshot_en'=>$person['name_en'],'name_snapshot_bn'=>$person['name_bn'],'designation_snapshot_en'=>$person['designation_en'],'designation_snapshot_bn'=>$person['designation_bn'],
            'home_location_id_snapshot'=>$person['home_location_id'],'home_company_id_snapshot'=>$person['home_company_id'],
            'is_external'=>$externalId || $person['home_location_id'] !== (int)$c->owner_location_id,
            'identified_via'=>$externalId ? 'EXTERNAL_RECORD' : (! empty($input['verification_code']) ? 'VERIFICATION_CODE' : 'PICKER'),
            'from_date'=>$from,'to_date'=>$to,'appointment_order_id'=>$order->id,'declaration_status'=>($c->policy_snapshot ?? $c->type->composition_policy)['declaration_required'] ? 'PENDING' : 'NOT_REQUIRED','created_by'=>auth()->id(),'remarks'=>$input['remarks'] ?? null]);
        $this->ledger->append($c,'MemberAppointed',['tenure_id'=>$tenure->id,'seat_id'=>$seatId,'user_id'=>$userId,'date'=>$from],$order->id); return $tenure;
    }
    public function release(string $id, int $tenureId, array $input): void
    {
        $this->committees->mutate($id,'committee.manage',['ACTIVE'],function ($c) use ($tenureId,$input) {
            $this->releaseLocked($c,$tenureId,$input); $this->afterChange($c,$input);
        });
    }
    private function releaseLocked(Committee $c, int $tenureId, array $input, ?string $transitionDate = null): CommitteeTenure
    {
        $t = $c->tenures()->findOrFail($tenureId);
        abort_unless($t->status === 'ACTIVE' && ! $t->release_order_id,409);
        abort_if($c->tenures()->where('corrects_tenure_id',$t->id)->exists(),409);
        Validator::make($input,['to_date'=>'required|date_format:Y-m-d|after_or_equal:'.$t->from_date,'release_reason'=>'required|in:TRANSFER,RETIREMENT,PROMOTION,RESIGNATION,REMOVAL,DEATH,TERM_END,RECONSTITUTION','reason'=>'required|string|min:5|max:1000'])->validate();
        abort_if($c->effective_to && $input['to_date'] > $c->effective_to,422);
        // A replacement starts on the order's effective date; the previous holder ends the day before.
        // Only internal cutover commands supply that date. A direct release still checks its own last day.
        $order = $this->committees->order($c,(int)($input['order_id'] ?? 0),['AMENDMENT','RECONSTITUTION'],$transitionDate ?? $input['to_date']);
        $t->update(['to_date'=>$input['to_date'],'status'=>'ENDED','release_order_id'=>$order->id,'release_reason'=>$input['release_reason'],'release_note'=>$input['reason']]);
        $this->ledger->append($c,'MemberReleased',['tenure_id'=>$t->id,'date'=>$t->to_date,'reason'=>$t->release_reason],$order->id,$input['reason']); return $t;
    }
    public function replace(string $id, int $seatId, array $input): CommitteeTenure
    {
        unset($input['_correcting_tenure_id']);
        return $this->committees->mutate($id,'committee.manage',['ACTIVE'],function ($c) use ($seatId,$input) {
            Validator::make($input,['from_date'=>'required|date_format:Y-m-d'])->validate();
            $corrected=$c->tenures()->pluck('corrects_tenure_id')->filter()->all();
            $t = $c->tenures()->whereNotIn('id',$corrected)->where('status','ACTIVE')->where('seat_id',$seatId)->where('from_date','<',$input['from_date'])->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date','>=',$input['from_date']))->firstOrFail();
            $outgoing = $this->releaseLocked($c,$t->id,['to_date'=>CarbonImmutable::parse($input['from_date'])->subDay()->toDateString()] + $input,$input['from_date']);
            $incoming = $this->appointLocked($c,$seatId,$input); $outgoing->update(['succeeded_by_tenure_id'=>$incoming->id]);
            $this->afterChange($c,$input);
            $this->ledger->append($c,'MemberReplaced',['outgoing'=>$outgoing->id,'incoming'=>$incoming->id,'date'=>$input['from_date']],(int)$input['order_id'],$input['reason'] ?? null); return $incoming;
        });
    }
    public function vacateForTransfer(string $id, int $seatId, int $userId, array $input): void
    {
        $this->committees->mutate($id,'committee.manage',['ACTIVE'],function ($c) use ($seatId,$userId,$input) {
            Validator::make($input,['from_date'=>'required|date_format:Y-m-d'])->validate();
            abort_if($c->effective_to && $input['from_date'] > $c->effective_to,422);
            $corrected=$c->tenures()->pluck('corrects_tenure_id')->filter()->all();
            $t=$c->tenures()->whereNotIn('id',$corrected)->where('seat_id',$seatId)->where('user_id',$userId)->where('status','ACTIVE')->where('from_date','<',$input['from_date'])
                ->where(fn ($q)=>$q->whereNull('to_date')->orWhere('to_date','>=',$input['from_date']))->firstOrFail();
            $this->releaseLocked($c,$t->id,['to_date'=>CarbonImmutable::parse($input['from_date'])->subDay()->toDateString()] + $input,$input['from_date']);
            $this->afterChange($c,$input);
        });
    }
    public function changeDraftHolder(string $id, int $seatId, array $input): CommitteeTenure
    {
        unset($input['_correcting_tenure_id']);
        return $this->committees->mutate($id,'committee.manage',['DRAFT'],function ($c) use ($seatId,$input) {
            $all = $c->tenures()->where('seat_id',$seatId)->get();
            $corrected = $all->pluck('corrects_tenure_id')->filter()->all();
            $old = $all->first(fn ($t) => ! in_array($t->id,$corrected));
            abort_unless($old,409);
            $new = $this->appointLocked($c,$seatId,['_correcting_tenure_id'=>$old->id] + $input);
            $new->update(['corrects_tenure_id'=>$old->id]);
            $this->ledger->append($c,'DraftUpdated',['original_tenure_id'=>$old->id,'replacement_tenure_id'=>$new->id],$new->appointment_order_id);
            return $new;
        });
    }
    public function correctTenure(string $id, int $tenureId, array $input): CommitteeTenure
    {
        return $this->committees->mutate($id,'committee.manage',['ACTIVE'],function ($c) use ($tenureId,$input) {
            Validator::make($input,['reason'=>'required|string|min:5|max:1000'])->validate();
            $old = $c->tenures()->findOrFail($tenureId);
            $order = $this->committees->order($c,(int)($input['order_id'] ?? 0),['CORRIGENDUM']);
            abort_if($c->tenures()->where('corrects_tenure_id',$old->id)->exists(),409);
            // Internal correction excludes the original without rewriting it.
            $new = $this->appointLocked($c,$old->seat_id,['_correcting_tenure_id'=>$old->id] + $input + ['from_date'=>$old->from_date,'to_date'=>$old->to_date]);
            $new->update(['corrects_tenure_id'=>$old->id]);
            $this->afterChange($c,$input);
            $this->ledger->append($c,'CommitteeAmended',['corrects_tenure_id'=>$old->id,'replacement'=>$new->id],$order->id,$input['reason'] ?? null); return $new;
        });
    }
    private function afterChange(Committee $c, array $input): void
    {
        if ($c->status === 'ACTIVE') {
            $this->committees->acknowledge($this->composition->evaluate($c->fresh(),$input['from_date'] ?? CarbonImmutable::parse($input['to_date'] ?? 'today')->addDay()->toDateString()),$input,true);
        }
    }
    public function recordDeclaration(string $id, int $tenureId, array $input, \Illuminate\Http\UploadedFile $file): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($id,$tenureId,$input,$file) {
            app(\GovStore\Committee\Policies\CommitteePolicy::class)->ability('committee.declare');
            $initial = Committee::findOrFail($id);
            Committee::where('lineage_id',$initial->lineage_id)->orderBy('version_no')->lockForUpdate()->firstOrFail();
            $c = Committee::whereKey($id)->lockForUpdate()->firstOrFail(); $t = $c->tenures()->findOrFail($tenureId);
            abort_unless(in_array($c->status,['DRAFT','ACTIVE']),409);
            abort_if($c->tenures()->where('corrects_tenure_id',$tenureId)->exists(),409);
            $userId = $t->user_id ?? ExternalMember::find($t->external_member_id)?->linked_user_id;
            if ($userId !== auth()->id()) {
                app(\GovStore\Committee\Policies\CommitteePolicy::class)->check($c,'committee.manage',true,['DRAFT','ACTIVE']);
                abort_unless(mb_strlen($input['reason'] ?? '') >= 5,422);
            }
            abort_if($t->declaration_status === 'FILED',409);
            Validator::make($input,['filed_on'=>'required|date_format:Y-m-d|after_or_equal:'.$t->from_date.'|before_or_equal:'.CarbonImmutable::now('Asia/Dhaka')->toDateString()])->validate();
            $attachment = app(OrderAttachmentStore::class)->store($file);
            $t->update(['declaration_status'=>'FILED','declaration_filed_on'=>$input['filed_on'],'declaration_attachment_path'=>$attachment['attachment_path'],'declaration_attachment_sha256'=>$attachment['attachment_sha256']]);
            $this->ledger->append($c,'DeclarationFiled',['tenure_id'=>$t->id,'filed_on'=>$input['filed_on']],reason:$input['reason'] ?? null);
            app(CommitteeHealthService::class)->refreshProjection($id);
        });
    }
}
