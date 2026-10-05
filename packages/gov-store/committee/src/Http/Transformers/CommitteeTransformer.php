<?php

namespace GovStore\Committee\Http\Transformers;

use GovStore\Committee\Models\Committee;

class CommitteeTransformer
{
    public function history($entry): array
    {
        $p = $entry->payload;
        $tenure = fn ($id) => \GovStore\Committee\Models\CommitteeTenure::where('committee_id',$entry->committee_id)->find($id);
        $incoming = $tenure($p['incoming'] ?? $p['tenure_id'] ?? $p['replacement'] ?? null);
        $outgoing = $tenure($p['outgoing'] ?? $p['corrects_tenure_id'] ?? null);
        $actor = \Illuminate\Support\Facades\DB::table('users')->where('id',$entry->actor_id)->first(['display_name','first_name','last_name']);
        $order = \GovStore\Committee\Models\CommitteeOrder::where('committee_id',$entry->committee_id)->find($entry->order_id);
        if (!$order && $entry->event_type === 'CommitteeReconstituted') {
            $successor=app(\GovStore\Committee\Scopes\CommitteeBoundaryScope::class)->apply(Committee::whereKey($p['new_committee_id'] ?? null)->where('lineage_id',$entry->lineage_id))->first();
            if ($successor) { $order=$successor->orders()->find($entry->order_id); }
        }
        $role = $incoming ? \GovStore\Committee\Models\CommitteeSeat::with('role')->find($incoming->seat_id)?->role : null;
        $display = \GovStore\Committee\Support\CommitteeDisplay::class;
        return ['sentence'=>__('committee::committee.ux.events.'.$entry->event_type,[
            'new'=>$display::text($incoming?->name_snapshot_bn,$incoming?->name_snapshot_en),
            'old'=>$display::text($outgoing?->name_snapshot_bn,$outgoing?->name_snapshot_en),
            'role'=>$display::text($role?->name_bn,$role?->name_en),
            'date'=>$display::date($p['date'] ?? $p['effective_to'] ?? null),
        ]),'actor'=>$actor ? ($actor->display_name ?: trim($actor->first_name.' '.$actor->last_name)) : __('committee::committee.ux.system'),
            'date'=>$display::date($entry->occurred_at,true),'reason'=>$entry->reason,'order'=>$order ? $this->order($order) : null,
            'group'=>str_contains($entry->event_type,'Member') ? 'members' : (str_contains($entry->event_type,'Amended') ? 'corrections' : 'state')];
    }
    public function row(Committee $c): array
    {
        return ['id'=>$c->id,'number'=>$c->committee_number,'name_en'=>$c->name_en,'name_bn'=>$c->name_bn,'type'=>$c->type->name_en,
            'status'=>$c->status,'effective_from'=>$c->effective_from,'effective_to'=>$c->effective_to,'url'=>route('committee.show',$c->id)];
    }
    public function order($o): array
    {
        return ['id'=>$o->id,'kind'=>$o->kind,'memo_no'=>$o->memo_no,'issued_on'=>$o->issued_on,'issued_on_bangla'=>$o->issued_on_bangla,'authority'=>$o->issuing_authority_name,
            'file_url'=>$o->attachment_path ? route('committee.order.file',$o->id) : null];
    }
}
