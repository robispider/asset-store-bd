<?php

namespace GovStore\Committee\Services;

use GovStore\Committee\Contracts\ScopeTypeRegistry;
use GovStore\Committee\DTOs\ScopeRef;
use GovStore\Committee\Models\CommitteeScope;
use GovStore\Committee\Policies\CommitteePolicy;
use Illuminate\Support\Facades\{DB,Validator};

class CommitteeAssignmentService
{
    public function __construct(private CommitteeService $committees, private CommitteePolicy $policy, private ScopeTypeRegistry $scopes, private CommitteeLedger $ledger) {}
    public function assignScope(string $id, array $input): CommitteeScope
    {
        return $this->committees->mutate($id,'committee.manage',['DRAFT','ACTIVE'],function ($c) use ($input) {
            $data = Validator::make($input,['scope_type'=>'required|string|max:40','scope_id'=>'required|string|max:64',
                'effective_from'=>'required|date_format:Y-m-d|after_or_equal:'.$c->effective_from,'effective_to'=>'nullable|date_format:Y-m-d|after_or_equal:effective_from','order_id'=>'required|integer'])->validate();
            $ref = new ScopeRef($data['scope_type'],$data['scope_id']); $this->policy->scope($ref);
            abort_unless(in_array($ref->type,$c->type->allowed_scope_types,true),422);
            abort_if($c->effective_to && ($data['effective_from'] > $c->effective_to || (! empty($data['effective_to']) && $data['effective_to'] > $c->effective_to)),422);
            $this->committees->order($c,(int)$data['order_id'],$c->status === 'DRAFT' ? ['CONSTITUTION','RECONSTITUTION'] : ['AMENDMENT'],$data['effective_from']);
            abort_if($c->scopes()->where('scope_type',$ref->type)->where('scope_id',$ref->id)->where('effective_from','<=',$data['effective_to'] ?? '9999-12-31')->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to','>=',$data['effective_from']))->exists(),409);
            $scope = $c->scopes()->create($data + ['scope_label_snapshot'=>$this->scopes->get($ref->type)->label($ref->id,'bn-BD'),'assigned_by'=>auth()->id()]);
            if ($c->status === 'ACTIVE' && ! $c->type->allow_concurrent) {
                \GovStore\Committee\Models\CommitteeType::whereKey($c->committee_type_id)->lockForUpdate()->firstOrFail();
                $this->committees->assertExclusiveCoverage($c);
                $existing = DB::table('gov_committee_active_slots')->where('committee_type_id',$c->committee_type_id)->where('scope_type',$ref->type)->where('scope_id',$ref->id)->lockForUpdate()->first();
                abort_if($existing && $existing->committee_id !== $c->id,409);
                if (! $existing) { DB::table('gov_committee_active_slots')->insert(['committee_type_id'=>$c->committee_type_id,'scope_type'=>$ref->type,'scope_id'=>$ref->id,'committee_id'=>$c->id]); }
            }
            $this->ledger->append($c,'CommitteeScopeAssigned',['scope_type'=>$ref->type,'scope_id'=>$ref->id,'date'=>$data['effective_from']],(int)$data['order_id']); return $scope;
        });
    }
    public function withdrawScope(string $id, int $scopeId, array $input): void
    {
        $this->committees->mutate($id,'committee.manage',['ACTIVE'],function ($c) use ($scopeId,$input) {
            $scope = $c->scopes()->findOrFail($scopeId);
            Validator::make($input,['effective_to'=>'required|date_format:Y-m-d|after_or_equal:'.$scope->effective_from,'reason'=>'required|string|min:5|max:1000'])->validate();
            abort_if($scope->effective_to,409);
            abort_if($c->effective_to && $input['effective_to'] > $c->effective_to,422);
            $order = $this->committees->order($c,(int)($input['order_id'] ?? 0),['AMENDMENT'],$input['effective_to']);
            $scope->update(['effective_to'=>$input['effective_to']]);
            $after = \Carbon\CarbonImmutable::parse($input['effective_to'])->addDay()->toDateString();
            $this->committees->acknowledge(app(CompositionValidator::class)->evaluate($c->fresh(),$after),$input,true);
            if ($input['effective_to'] < now('Asia/Dhaka')->toDateString()) { DB::table('gov_committee_active_slots')->where('committee_id',$c->id)->where('scope_type',$scope->scope_type)->where('scope_id',$scope->scope_id)->delete(); }
            $this->ledger->append($c,'CommitteeScopeWithdrawn',['scope_id'=>$scopeId,'date'=>$input['effective_to']],$order->id,$input['reason']);
        });
    }
}
