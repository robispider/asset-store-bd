<?php

namespace GovStore\Committee\Http\Transformers;

use GovStore\Committee\Models\Committee;

class CommitteeTransformer
{
    public function row(Committee $c): array
    {
        return ['id'=>$c->id,'number'=>$c->committee_number,'name_en'=>$c->name_en,'name_bn'=>$c->name_bn,'type'=>$c->type->name_en,
            'status'=>$c->status,'effective_from'=>$c->effective_from,'effective_to'=>$c->effective_to,'url'=>route('committee.show',$c->id)];
    }
    public function order($o): array
    {
        return ['id'=>$o->id,'kind'=>$o->kind,'memo_no'=>$o->memo_no,'issued_on'=>$o->issued_on,'authority'=>$o->issuing_authority_name,
            'file_url'=>$o->attachment_path ? route('committee.order.file',$o->id) : null];
    }
}
