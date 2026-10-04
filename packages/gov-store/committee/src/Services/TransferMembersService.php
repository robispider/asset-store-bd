<?php

namespace GovStore\Committee\Services;

use Illuminate\Support\Facades\{DB,Validator};

class TransferMembersService
{
    public function __construct(private CommitteeMembershipService $members) {}
    public function apply(array $input): array
    {
        Validator::make($input,[
            'user_id'=>'required|integer','changes'=>'required|array|min:1|max:20',
            'changes.*.committee_id'=>'required|uuid','changes.*.seat_id'=>'required|integer','changes.*.order_id'=>'required|integer',
            'changes.*.from_date'=>'required|date_format:Y-m-d','changes.*.user_id'=>'required|integer','reason'=>'required|string|min:5|max:1000',
        ])->validate();
        return DB::transaction(function () use ($input) {
            $changes = collect($input['changes']);
            $types = \GovStore\Committee\Models\Committee::whereIn('id',$changes->pluck('committee_id'))->pluck('committee_type_id')->unique()->sort();
            foreach ($types as $type) { \GovStore\Committee\Models\CommitteeType::whereKey($type)->lockForUpdate()->firstOrFail(); }
            // Acquire every stable lineage mutex in one order before any version
            // row; individual changes then reuse the same locks.
            $lineages = \GovStore\Committee\Models\Committee::whereIn('id',$changes->pluck('committee_id'))->pluck('lineage_id')->unique()->sort();
            foreach ($lineages as $lineage) {
                \GovStore\Committee\Models\Committee::where('lineage_id',$lineage)->orderBy('version_no')->lockForUpdate()->firstOrFail();
            }
            $changes = $changes->sortBy('committee_id');
            $memo = null; $results = [];
            foreach ($changes as $change) {
                $c = \GovStore\Committee\Models\Committee::whereKey($change['committee_id'])->lockForUpdate()->firstOrFail();
                app(\GovStore\Committee\Policies\CommitteePolicy::class)->check($c,'committee.manage',true,['ACTIVE']);
                $outgoing = $c->tenures()->where('seat_id',$change['seat_id'])->where('user_id',$input['user_id'])->where('status','ACTIVE')
                    ->where('from_date','<',$change['from_date'])->where(fn ($q) => $q->whereNull('to_date')->orWhere('to_date','>=',$change['from_date']))->firstOrFail();
                $order = $c->orders()->findOrFail($change['order_id']);
                abort_unless($order->kind === 'AMENDMENT',422);
                if ($memo !== null) { abort_unless($memo === $order->memo_no_normalized,422); } $memo = $order->memo_no_normalized;
                $t = $this->members->replace($c->id,$change['seat_id'],$change + ['release_reason'=>'TRANSFER','reason'=>$input['reason'],'acknowledgement'=>$input['acknowledgement'] ?? '','acknowledged'=>$input['acknowledged'] ?? []]);
                $results[] = $t->id;
            }
            return ['saved'=>true,'tenure_ids'=>$results];
        });
    }
}
