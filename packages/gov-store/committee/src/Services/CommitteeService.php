<?php

namespace GovStore\Committee\Services;

use Carbon\CarbonImmutable;
use GovStore\Committee\Domain\MemoNumber;
use GovStore\Committee\Models\{Committee,CommitteeType,CommitteeOrder,CommitteeScope,CommitteeSeat,CommitteeTenure};
use GovStore\Committee\Policies\CommitteePolicy;
use GovStore\Committee\Repositories\CommitteeRepository;
use GovStore\TenantScope\Contexts\TenantContext;
use Illuminate\Support\Facades\{DB,Validator,Storage};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommitteeService
{
    public function __construct(private CommitteeRepository $repository, private CommitteePolicy $policy, private TenantContext $context,
        private CommitteeNumberService $numbers, private CommitteeLedger $ledger, private CompositionValidator $composition, private CommitteeHealthService $health) {}

    public function createDraft(array $input, ?Committee $supersedes = null): Committee
    {
        $this->policy->ability('committee.manage');
        abort_unless($this->context->locationId && $this->context->companyId,422);
        $data = $this->draftData($input);
        return DB::transaction(function () use ($data,$supersedes) {
            $type = CommitteeType::whereKey($data['committee_type_id'])->lockForUpdate()->firstOrFail();
            abort_unless($type->is_active && (! $type->owner_company_id || (int)$type->owner_company_id === $this->context->companyId),404);
            $from = CarbonImmutable::parse($data['effective_from']);
            $year = $from->month >= 7 ? $from->year : $from->year-1;
            $fy = $year.'-'.substr((string)($year+1),2);
            $c = Committee::create($data + ['lineage_id'=>$supersedes?->lineage_id ?? (string)Str::uuid(),'version_no'=>$supersedes ? Committee::withTrashed()->where('lineage_id',$supersedes->lineage_id)->max('version_no')+1 : 1,'supersedes_id'=>$supersedes?->id,'committee_number'=>$this->numbers->next($this->context->locationId,$fy),
                'owner_location_id'=>$this->context->locationId,'owner_company_id'=>$this->context->companyId,'status'=>'DRAFT','fiscal_year'=>$fy,'created_by'=>auth()->id()]);
            $this->ledger->append($c,'DraftCreated',['date'=>$c->effective_from]);
            return $c;
        });
    }
    private function draftData(array $input): array
    {
        $data = Validator::make($input,[
            'committee_type_id'=>'required|integer','name_en'=>'required|string|min:5|max:200','name_bn'=>'required|string|min:5|max:200',
            'term_basis'=>'required|in:FIXED,FISCAL_YEAR,SINGLE_MATTER,UNTIL_FURTHER_ORDER',
            'effective_from'=>'required|date_format:Y-m-d|after_or_equal:'.CarbonImmutable::now('Asia/Dhaka')->subYears(5)->toDateString(),
            'effective_to'=>'nullable|date_format:Y-m-d|after_or_equal:effective_from','terms_of_reference'=>'nullable|string|max:10000',
        ])->validate();
        if ($data['term_basis'] === 'FISCAL_YEAR') {
            $from = CarbonImmutable::parse($data['effective_from']); $year = $from->month >= 7 ? $from->year+1 : $from->year;
            $data['effective_to'] = $year.'-06-30';
        } elseif ($data['term_basis'] === 'UNTIL_FURTHER_ORDER') { $data['effective_to'] = null; }
        elseif (! ($data['effective_to'] ?? null)) { throw ValidationException::withMessages(['effective_to'=>__('committee::committee.end_required')]); }
        return $data;
    }
    public function updateDraft(string $id, array $input): Committee
    {
        return $this->mutate($id,'committee.manage',['DRAFT'],function ($c) use ($input) {
            abort_unless(isset($input['lock_version']) && (int)$input['lock_version'] === $c->lock_version,409);
            $data = $this->draftData($input);
            // Changing type after composing seats/coverage is unsafe; re-create the draft instead.
            abort_unless((int)$data['committee_type_id'] === (int)$c->committee_type_id,409);
            abort_if($c->tenures()->where('from_date','<',$data['effective_from'])->exists() || $c->scopes()->where('effective_from','<',$data['effective_from'])->exists(),422);
            if ($data['effective_to']) {
                abort_if($c->tenures()->where('from_date','>',$data['effective_to'])->exists() || $c->scopes()->where('effective_from','>',$data['effective_to'])->exists(),422);
            }
            $c->fill($data); $c->lock_version++; $c->save();
            $this->ledger->append($c,'DraftUpdated',['lock_version'=>$c->lock_version]); return $c;
        });
    }
    public function discardDraft(string $id, string $reason): void
    {
        $this->mutate($id,'committee.manage',['DRAFT'],function ($c) use ($reason) { abort_unless(mb_strlen($reason)>=5,422); $this->ledger->append($c,'DraftDiscarded',[],reason:$reason); $c->delete(); });
    }
    public function mutate(string $id, string $ability, array $states, callable $callback): mixed
    {
        return DB::transaction(function () use ($id,$ability,$states,$callback) {
            $initial = Committee::findOrFail($id);
            // Serialize type-level exclusivity before locking any lineage. This
            // also gives historical overlap checks a current locking read.
            CommitteeType::whereKey($initial->committee_type_id)->lockForUpdate()->firstOrFail();
            // Consistent lock ordering: stable lineage root first, then the selected version.
            Committee::where('lineage_id',$initial->lineage_id)->orderBy('version_no')->lockForUpdate()->firstOrFail();
            $c = $this->repository->lockForUpdate($id);
            $this->policy->check($c,$ability,true,$states);
            $result = $callback($c);
            if (! $c->trashed()) { $this->health->refreshProjection($id); }
            return $result;
        });
    }
    public function order(Committee $c, int $id, array $kinds = [], ?string $date = null): CommitteeOrder
    {
        $order = $c->orders()->findOrFail($id);
        abort_if($kinds && ! in_array($order->kind,$kinds,true),422);
        if ($date) { abort_if($order->issued_on > $date,422); }
        return $order;
    }
    public function addOrder(string $id, array $input, ?\Illuminate\Http\UploadedFile $file): CommitteeOrder
    {
        return $this->mutate($id,'committee.manage',['DRAFT','ACTIVE','SUSPENDED','EXPIRED'],function ($c) use ($input,$file) {
            $data = Validator::make($input,[
                'kind'=>'required|in:CONSTITUTION,AMENDMENT,RECONSTITUTION,EXTENSION,SUSPENSION,RESUMPTION,DISSOLUTION,CORRIGENDUM',
                'memo_no'=>'required|string|min:5|max:150','office_order_no'=>'nullable|string|max:100','nothi_no'=>'nullable|string|max:150',
                'issued_on'=>'required|date_format:Y-m-d|before_or_equal:'.CarbonImmutable::now('Asia/Dhaka')->toDateString(),
                'issuing_authority_name'=>'required|string|max:150','issuing_authority_designation_en'=>'required|string|max:150',
                'issuing_authority_designation_bn'=>'nullable|string|max:150','issued_on_bangla'=>'nullable|string|max:60','remarks'=>'nullable|string|max:1000',
            ])->validate();
            abort_unless($file,422);
            $attachment = app(OrderAttachmentStore::class)->store($file);
            try {
                $order = $c->orders()->create($data + $attachment + ['memo_no_normalized'=>MemoNumber::normalize($data['memo_no']),'issuing_location_id'=>$c->owner_location_id,'recorded_by'=>auth()->id(),'recorded_at'=>now()]);
                $this->ledger->append($c,'OrderRecorded',['order_id'=>$order->id,'sha256'=>$order->attachment_sha256],$order->id);
                return $order;
            } catch (\Throwable $e) { Storage::disk('committee_private')->delete($attachment['attachment_path']); throw $e; }
        });
    }
    public function reserveSlots(Committee $c): void
    {
        if ($c->type->allow_concurrent) { return; }
        foreach ($c->scopes()->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to','>=',CarbonImmutable::now('Asia/Dhaka')->toDateString()))->get()->unique(fn ($s) => $s->scope_type.':'.$s->scope_id) as $scope) {
            // This insert is the cross-request exclusivity guard. Never ignore a duplicate.
            try { DB::table('gov_committee_active_slots')->insert(['committee_type_id'=>$c->committee_type_id,'scope_type'=>$scope->scope_type,'scope_id'=>$scope->scope_id,'committee_id'=>$c->id]); }
            catch (\Illuminate\Database\UniqueConstraintViolationException $e) { abort(409); }
        }
    }
    public function assertExclusiveCoverage(Committee $c, ?string $newEnd = null): void
    {
        if ($c->type->allow_concurrent) { return; }
        $others = Committee::where('committee_type_id',$c->committee_type_id)->whereKeyNot($c->id)->whereNotNull('activated_at')->lockForUpdate()->get();
        foreach ($c->scopes()->get() as $scope) {
            $from = max($c->effective_from,$scope->effective_from);
            $to = min($newEnd ?? $c->effective_to ?? '9999-12-31',$c->ended_on ?? '9999-12-31',$scope->effective_to ?? '9999-12-31');
            foreach ($others as $other) {
                foreach ($other->scopes()->lockForUpdate()->get() as $candidate) {
                    if ($scope->scope_type !== $candidate->scope_type || $scope->scope_id !== $candidate->scope_id) { continue; }
                    $otherFrom = max($other->effective_from,$candidate->effective_from);
                    $otherTo = min($other->effective_to ?? '9999-12-31',$other->ended_on ?? '9999-12-31',$candidate->effective_to ?? '9999-12-31');
                    abort_if($from <= $to && $otherFrom <= $otherTo && $from <= $otherTo && $otherFrom <= $to,409);
                }
            }
        }
    }
    public function activate(string $id, array $input): Committee
    {
        return $this->mutate($id,'committee.activate',['DRAFT'],function ($c) use ($input) {
            $order = $this->order($c,(int)($input['order_id'] ?? 0),[$c->supersedes_id ? 'RECONSTITUTION' : 'CONSTITUTION'],$c->effective_from);
            abort_unless($order->attachment_path,422);
            $type = CommitteeType::whereKey($c->committee_type_id)->lockForUpdate()->firstOrFail();
            abort_unless($type->is_active,422);
            $c->policy_snapshot = $type->composition_policy; $c->type_policy_version = $type->policy_version;
            $findings = $this->composition->evaluate($c,$c->effective_from);
            $this->acknowledge($findings,$input);
            if ($c->supersedes_id) {
                $old = $this->repository->lockForUpdate($c->supersedes_id);
                $this->policy->check($old,'committee.activate',true,['ACTIVE','EXPIRED']);
                abort_unless($c->effective_from > $old->effective_from,422);
                $old->update(['status'=>'SUPERSEDED','ended_on'=>CarbonImmutable::parse($c->effective_from)->subDay()->toDateString(),'end_reason'=>'RECONSTITUTION']);
                DB::table('gov_committee_active_slots')->where('committee_id',$old->id)->delete();
                $this->ledger->append($old,'CommitteeReconstituted',['new_committee_id'=>$c->id,'date'=>$c->effective_from],$order->id);
            }
            $this->assertExclusiveCoverage($c);
            $this->reserveSlots($c);
            $c->status = $c->effective_to && $c->effective_to < CarbonImmutable::now('Asia/Dhaka')->toDateString() ? 'EXPIRED' : 'ACTIVE';
            $c->constitution_order_id = $order->id; $c->activated_by = auth()->id(); $c->activated_at = now(); $c->save();
            if ($c->status === 'EXPIRED') { DB::table('gov_committee_active_slots')->where('committee_id',$c->id)->delete(); }
            $this->ledger->append($c,'CommitteeActivated',['date'=>$c->effective_from,'findings'=>$findings,'acknowledged'=>$input['acknowledged'] ?? []],$order->id,$input['reason'] ?? null);
            return $c;
        });
    }
    public function acknowledge(array $findings, array $input, bool $allowInoperable = false): void
    {
        $blocks = array_filter($findings,fn ($f) => $f['severity'] === 'BLOCK');
        if ($blocks && ! $allowInoperable) { throw ValidationException::withMessages(['composition'=>array_column($blocks,'message_bn')]); }
        if ($blocks && (($input['acknowledgement'] ?? '') !== 'INOPERABLE' || mb_strlen($input['reason'] ?? '') < 5)) { throw ValidationException::withMessages(['acknowledgement'=>__('committee::committee.acknowledge_inoperable')]); }
        $warnings = array_column(array_filter($findings,fn ($f) => $f['severity'] === 'WARN'),'code');
        if (array_diff($warnings,$input['acknowledged'] ?? []) || ($warnings && mb_strlen($input['reason'] ?? '') < 5)) { throw ValidationException::withMessages(['acknowledged'=>__('committee::committee.acknowledge_warnings')]); }
    }
    public function changeState(string $id, string $action, array $input): Committee
    {
        $rules = ['suspend'=>[['ACTIVE'],'SUSPENSION','SUSPENDED','CommitteeSuspended'], 'resume'=>[['SUSPENDED'],'RESUMPTION','ACTIVE','CommitteeResumed'],
            'dissolve'=>[['ACTIVE','SUSPENDED'],'DISSOLUTION','DISSOLVED','CommitteeDissolved'], 'extend'=>[['ACTIVE','EXPIRED'],'EXTENSION','ACTIVE','CommitteeTermExtended']];
        abort_unless(isset($rules[$action]),404); [$states,$kind,$status,$event] = $rules[$action];
        return $this->mutate($id,'committee.activate',$states,function ($c) use ($input,$action,$kind,$status,$event) {
            Validator::make($input,['date'=>'required|date_format:Y-m-d|after_or_equal:'.$c->effective_from,'order_id'=>'required|integer','reason'=>'required|string|min:5|max:1000'])->validate();
            $order = $this->order($c,(int)$input['order_id'],[$kind],$input['date']);
            abort_if($input['date'] > CarbonImmutable::now('Asia/Dhaka')->toDateString(),422);
            if ($action !== 'extend') { abort_if($c->effective_to && $input['date'] > $c->effective_to,422); }
            if ($action === 'dissolve') {
                abort_unless(($input['confirmation'] ?? '') === $c->committee_number,422);
                $c->ended_on = $input['date']; $c->end_reason = 'DISSOLUTION';
                DB::table('gov_committee_active_slots')->where('committee_id',$id = $c->id)->delete();
            }
            if ($action === 'extend') {
                Validator::make($input,['effective_to'=>'required|date_format:Y-m-d|after:'.$c->effective_to])->validate();
                abort_unless($c->effective_to && $c->term_basis !== 'UNTIL_FURTHER_ORDER',422);
                CommitteeType::whereKey($c->committee_type_id)->lockForUpdate()->firstOrFail();
                abort_if($input['effective_to'] < CarbonImmutable::now('Asia/Dhaka')->toDateString(),422);
                $c->ended_on = null;
                $this->assertExclusiveCoverage($c,$input['effective_to']);
                if ($c->status === 'EXPIRED') {
                    abort_if(CarbonImmutable::parse($c->effective_to)->addDays(config('committee.extension_grace_days'))->toDateString() < CarbonImmutable::now('Asia/Dhaka')->toDateString(),409);
                    $this->reserveSlots($c); $c->ended_on = null; $c->end_reason = null;
                }
                $c->effective_to = $input['effective_to'];
                $this->acknowledge($this->composition->evaluate($c,$input['date']),$input);
            }
            $c->status = $status; $c->save();
            $this->ledger->append($c,$event,['date'=>$input['date'],'effective_to'=>$c->effective_to],$order->id,$input['reason']); return $c;
        });
    }
    public function startReconstitution(string $id, array $input): Committee
    {
        return $this->mutate($id,'committee.manage',['ACTIVE','EXPIRED'],function ($old) use ($input) {
            $data = $this->draftData($input + ['committee_type_id'=>$old->committee_type_id,'name_en'=>$old->name_en,'name_bn'=>$old->name_bn,'term_basis'=>$old->term_basis]);
            abort_unless($data['effective_from'] > $old->effective_from,422);
            $c = $this->createDraft($data,$old);
            foreach ($old->seats as $seat) { $c->seats()->create($seat->only(['seat_role_code','seat_no','holder_kind','post_title_en','post_title_bn','post_location_id','is_required']) + ['created_by'=>auth()->id()]); }
            $this->ledger->append($c,'ReconstitutionStarted',['supersedes_id'=>$old->id,'date'=>$c->effective_from]);
            return $c;
        });
    }
    public function expireDue(): int
    {
        $count = 0;
        foreach (Committee::whereIn('status',['ACTIVE','SUSPENDED'])->where('effective_to','<',CarbonImmutable::now('Asia/Dhaka')->toDateString())->pluck('id') as $id) {
            DB::transaction(function () use ($id,&$count) {
                $initial = Committee::findOrFail($id);
                Committee::where('lineage_id',$initial->lineage_id)->orderBy('version_no')->lockForUpdate()->firstOrFail();
                $c = $this->repository->lockForUpdate($id);
                if (! in_array($c->status,['ACTIVE','SUSPENDED']) || $c->effective_to >= CarbonImmutable::now('Asia/Dhaka')->toDateString()) { return; }
                $c->update(['status'=>'EXPIRED','ended_on'=>$c->effective_to,'end_reason'=>'TERM_END']);
                DB::table('gov_committee_active_slots')->where('committee_id',$id)->delete();
                $this->ledger->append($c,'CommitteeExpired',['date'=>$c->effective_to]); $count++;
            });
        }
        // A dated withdrawal frees its current slot on the following day, even
        // when the committee itself continues covering other offices.
        foreach (Committee::whereIn('status',['ACTIVE','SUSPENDED'])->pluck('id') as $id) {
            DB::transaction(function () use ($id) {
                $initial = Committee::findOrFail($id);
                Committee::where('lineage_id',$initial->lineage_id)->orderBy('version_no')->lockForUpdate()->firstOrFail();
                $c = $this->repository->lockForUpdate($id);
                foreach ($c->scopes()->whereNotNull('effective_to')->where('effective_to','<',CarbonImmutable::now('Asia/Dhaka')->toDateString())->get() as $scope) {
                    DB::table('gov_committee_active_slots')->where('committee_id',$id)->where('scope_type',$scope->scope_type)->where('scope_id',$scope->scope_id)->delete();
                }
            });
        }
        return $count;
    }
}
