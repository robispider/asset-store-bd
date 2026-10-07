<section class="box"><h2>{{ __('committee::committee.ux.committee_identity') }}</h2>
@if(!$typeOptions)<p class="alert alert-warning">{{ __('committee::committee.ux.no_active_types') }}</p>@endif
@include('committee::field',['name'=>'committee_type_id','label'=>'type','options'=>$typeOptions,'value'=>$c?->committee_type_id,'required'=>true,'class'=>'cm-type-choice'])
<div class="row"><div class="col-md-6">@include('committee::field',['name'=>'name_bn','value'=>$c?->name_bn,'required'=>true])</div><div class="col-md-6">@include('committee::field',['name'=>'name_en','value'=>$c?->name_en,'required'=>true])</div></div>
@include('committee::field',['name'=>'term_basis','options'=>$enumOptions('FISCAL_YEAR FIXED UNTIL_FURTHER_ORDER SINGLE_MATTER'),'value'=>$c?->term_basis,'class'=>'cm-term-basis'])
<div class="row"><div class="col-md-6">@include('committee::field',['name'=>'effective_from','type'=>'date','value'=>$mode === 'reconstitute' ? now('Asia/Dhaka')->toDateString() : ($c?->effective_from ?? now('Asia/Dhaka')->toDateString()),'required'=>true])</div><div class="col-md-6">@include('committee::field',['name'=>'effective_to','type'=>'date','value'=>$c?->effective_to])</div></div>
@include('committee::field',['name'=>'terms_of_reference','value'=>$c?->terms_of_reference,'type'=>'textarea'])
<p class="cm-subtle"><i class="fa fa-building-o" aria-hidden="true"></i> {{ __('committee::committee.ux.this_office_preselected') }}: {{ $officeName }}</p>
@if($mode === 'reconstitute')<p class="alert alert-info">{{ __('committee::committee.ux.reconstitution_help') }}</p>@endif
<script type="application/json" class="cm-types-data">{!! json_encode($types->map(fn ($t) => ['id'=>$t->id,'name_bn'=>$t->name_bn,'name_en'=>$t->name_en,'term'=>$t->default_term_basis])->values(),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
</section>
