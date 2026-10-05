<p class="cm-muted">{{ __('committee::committee.ux.paper_help') }}</p>
@include('committee::field',['name'=>'file','required'=>true,'type'=>'file','label'=>'ux.signed_order'])
@include('committee::field',['name'=>'memo_no','required'=>true,'label'=>'ux.memo'])
<div class="row"><div class="col-sm-6">@include('committee::field',['name'=>'issued_on','type'=>'date','value'=>now('Asia/Dhaka')->toDateString(),'required'=>true])</div><div class="col-sm-6">@include('committee::field',['name'=>'office_order_no'])</div></div>
@include('committee::field',['name'=>'issuing_authority_name','required'=>true,'label'=>'ux.signatory'])
@include('committee::field',['name'=>'issuing_authority_designation_en','required'=>true])
<details><summary>{{ __('committee::committee.ux.more_order_fields') }}</summary>@include('committee::field',['name'=>'nothi_no'])@include('committee::field',['name'=>'issued_on_bangla','label'=>'ux.bangla_date'])</details>
